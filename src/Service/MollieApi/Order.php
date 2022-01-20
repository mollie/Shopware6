<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeFetchedException;
use Kiener\MolliePayments\Exception\MollieOrderPaymentCouldNotBeCreatedException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\MollieApi\Payment as PaymentApiService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;
use Mollie\Api\Types\OrderLineType;
use Mollie\Api\Types\PaymentStatus;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class Order
{
    /**
     * @var MollieApiFactory
     */
    private $clientFactory;

    /**
     * @var PaymentApiService
     */
    private $paymentApiService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    public function __construct(MollieApiFactory $clientFactory, PaymentApiService $paymentApiService, LoggerInterface $logger)
    {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
        $this->paymentApiService = $paymentApiService;
    }

    /**
     * @param string $mollieOrderId
     * @param string|null $salesChannelId
     * @param array $parameters
     * @return MollieOrder
     * @throws CouldNotFetchMollieOrderException
     */
    public function getMollieOrder(string $mollieOrderId, string $salesChannelId, array $parameters = []): MollieOrder
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            return $apiClient->orders->get($mollieOrderId, $parameters);
        } catch (ApiException $e) {

            $this->logger->error(
                sprintf(
                    'API error occurred when fetching mollie order %s with message %s',
                    $mollieOrderId,
                    $e->getMessage()
                )
            );

            throw new CouldNotFetchMollieOrderException($mollieOrderId, $e);
        }
    }

    /**
     * @param string $mollieOrderId
     * @param string $mollieOrderLineId
     * @param string $salesChannelId
     * @return OrderLine
     * @throws CouldNotFetchMollieOrderException
     */
    public function getMollieOrderLine(
        string  $mollieOrderId,
        string  $mollieOrderLineId,
        string  $salesChannelId
    ): OrderLine
    {
        return $this->getMollieOrder($mollieOrderId, $salesChannelId)->lines()->get($mollieOrderLineId);
    }

    public function createOrder(array $orderData, string $orderSalesChannelContextId, SalesChannelContext $salesChannelContext): MollieOrder
    {
        $apiClient = $this->clientFactory->getClient($orderSalesChannelContextId);

        /**
         * Create an order at Mollie based on the prepared array of order data.
         */
        try {
            return $apiClient->orders->create($orderData);
        } catch (ApiException $e) {

            $this->logger->critical(
                $e->getMessage(),
                [
                    'function' => 'finalize-payment',
                ]
            );

            throw new RuntimeException(sprintf('Could not create Mollie order, error: %s', $e->getMessage()));
        }
    }

    /**
     * function creates a new payment at mollie
     *
     * if an open payment in mollie order exists, this payment is used but is updated by correct payment method
     *
     * @param string $mollieOrderId
     * @param string $paymentMethod
     * @param PaymentHandler $paymentHandler
     * @param CustomerEntity $customer
     * @param SalesChannelContext $salesChannelContext
     * @return Payment
     * @throws ApiException
     */
    public function createOrReusePayment(
        string $mollieOrderId,
        string $paymentMethod,
        PaymentHandler $paymentHandler,
        OrderEntity $order,
        CustomerEntity $customer,
        SalesChannelContext $salesChannelContext
    ): Payment
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelContext->getSalesChannel()->getId(), ['embed' => 'payments']);

        if (!$mollieOrder instanceof MollieOrder) {

            throw new MollieOrderCouldNotBeFetchedException($mollieOrderId);
        }

        $payment = $this->getOpenPayment($mollieOrder);

        if (!$payment instanceof Payment) {

            $this->logger->debug(
                'Didn\'t find an open payment. Creating a new payment at mollie',
                [
                    'saleschannel' => $salesChannelContext->getSalesChannel()->getName(),
                ]
            );

            return $this->prepareNewPayment($mollieOrder, $paymentMethod, $paymentHandler, $order, $customer, $salesChannelContext);
        }

        if ($payment->method === $paymentMethod) {

            $this->logger->debug(
                'Found an open payment and payment methods are same. Reusing this payment',
                [
                    'saleschannel' => $salesChannelContext->getSalesChannel()->getName(),
                ]
            );

            return $payment;
        }

        if (!$payment->isCancelable) {

            $this->logger->debug(
                'Found an open payment but it isn\'t cancelable. Reusing this payment, otherwise we could never complete payment',
                [
                    'saleschannel' => $salesChannelContext->getSalesChannel()->getName(),
                ]
            );

            return $payment;
        }

        try {

            $this->logger->debug(
                'Found an open payment and cancel it. Create new payment with new payment method',
                [
                    'saleschannel' => $salesChannelContext->getSalesChannel()->getName(),
                ]
            );

            $this->paymentApiService->delete($payment->id, $salesChannelContext->getSalesChannel()->getId());

            /** @var Payment $payment */
            return $this->prepareNewPayment($mollieOrder, $paymentMethod, $paymentHandler, $order, $customer, $salesChannelContext);
        } catch (ApiException $e) {

            throw new MollieOrderPaymentCouldNotBeCreatedException($mollieOrderId, [], $e);
        }
    }

    private function getOpenPayment(MollieOrder $mollieOrder): ?Payment
    {
        $payments = $mollieOrder->payments();

        if (!$payments instanceof PaymentCollection) {
            return null;
        }

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($payment->isOpen()) {

                return $payment;
            }
        }

        return null;
    }

    /**
     * @param MollieOrder $mollieOrder
     * @param string $paymentMethod
     * @param PaymentHandler $paymentHandler
     * @param OrderEntity $order
     * @param CustomerEntity $customer
     * @param SalesChannelContext $salesChannelContext
     * @return Payment
     * @throws ApiException
     */
    private function prepareNewPayment(
        MollieOrder    $mollieOrder,
        string         $paymentMethod,
        PaymentHandler $paymentHandler,
        OrderEntity    $order,
        CustomerEntity $customer,
        SalesChannelContext $salesChannelContext
    ): Payment
    {
        // To add payment method specific parameters to our payment data, the method requires an array named orderData
        // We have no interest in order data, just the payment data that is inside it, but we still need to pass
        // along an array with a key 'payment'. This is our fake order data array.
        $fakeOrder = [
            'payment' => [
                'method' => $paymentMethod
            ]
        ];

        $fakeOrder = $paymentHandler->processPaymentMethodSpecificParameters(
            $fakeOrder,
            $order,
            $salesChannelContext,
            $customer
        );

        return $mollieOrder->createPayment($fakeOrder['payment']);
    }

    public function getPaymentUrl(string $mollieOrderId, string $salesChannelId): ?string
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId);

        return $mollieOrder->status === 'created' ? $mollieOrder->getCheckoutUrl() : null;
    }

    public function setShipment(string $mollieOrderId, string $salesChannelId): bool
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId);

        /** @var OrderLine $orderLine */
        foreach ($mollieOrder->lines() as $orderLine) {
            if ($orderLine->shippableQuantity > 0) {
                $mollieOrder->shipAll();

                return true;
            }
        }

        return false;
    }

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @return bool
     * @throws CouldNotFetchMollieOrderException
     */
    public function isCompletelyShipped(string $mollieOrderId, string $salesChannelId): bool
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId);

        /** @var OrderLine $mollieOrderLine */
        foreach ($mollieOrder->lines() as $mollieOrderLine) {
            if ($mollieOrderLine->shippableQuantity > 0 &&
                in_array($mollieOrderLine->type, [
                    OrderLineType::TYPE_PHYSICAL,
                    OrderLineType::TYPE_DIGITAL,
                    OrderLineType::TYPE_DISCOUNT,
                    OrderLineType::TYPE_STORE_CREDIT,
                ])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $mollieOrderId
     * @param string|null $salesChannelId
     * @return Payment
     */
    public function getCompletedPayment(string $mollieOrderId, ?string $salesChannelId): Payment
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId, ['embed' => 'payments']);

        if ($mollieOrder->payments()->count() === 0) {
            throw new PaymentNotFoundException($mollieOrderId);
        }

        /** @var Payment $payment */
        foreach ($mollieOrder->payments()->getArrayCopy() as $payment) {
            if (in_array($payment->status, [
                PaymentStatus::STATUS_PAID,
                PaymentStatus::STATUS_AUTHORIZED // Klarna
            ])) {
                return $payment;
            }
        }

        throw new PaymentNotFoundException($mollieOrderId);
    }
}
