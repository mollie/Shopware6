<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\MollieOrderCouldNotBeFetchedException;
use Kiener\MolliePayments\Exception\MollieOrderPaymentCouldNotBeCreatedException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Payment as PaymentApiService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;
use Mollie\Api\Types\OrderLineType;
use Mollie\Api\Types\PaymentStatus;
use Monolog\Logger;
use RuntimeException;
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
     * @var LoggerService
     */
    private $logger;


    public function __construct(MollieApiFactory $clientFactory, PaymentApiService $paymentApiService, LoggerService $logger)
    {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
        $this->paymentApiService = $paymentApiService;
    }

    /**
     * @param string $mollieOrderId
     * @param string|null $salesChannelId
     * @param Context $context
     * @param array $parameters
     * @return MollieOrder
     * @throws CouldNotFetchMollieOrderException
     */
    public function getMollieOrder(string $mollieOrderId, string $salesChannelId, Context $context, array $parameters = []): MollieOrder
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            return $apiClient->orders->get($mollieOrderId, $parameters);
        } catch (ApiException $e) {
            $this->logger->addEntry(
                sprintf(
                    'API error occurred when fetching mollie order %s with message %s',
                    $mollieOrderId,
                    $e->getMessage()
                ),
                $context,
                $e,
                null,
                Logger::ERROR
            );

            throw new CouldNotFetchMollieOrderException($mollieOrderId, $e);
        }
    }

    /**
     * @param string $mollieOrderId
     * @param string $mollieOrderLineId
     * @param string $salesChannelId
     * @param Context $context
     * @return OrderLine
     * @throws CouldNotFetchMollieOrderException
     */
    public function getMollieOrderLine(
        string $mollieOrderId,
        string $mollieOrderLineId,
        string $salesChannelId,
        Context $context
    ): OrderLine
    {
        return $this->getMollieOrder($mollieOrderId, $salesChannelId, $context)->lines()->get($mollieOrderLineId);
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
            $this->logger->addEntry(
                $e->getMessage(),
                $salesChannelContext->getContext(),
                $e,
                [
                    'function' => 'finalize-payment',
                ],
                Logger::CRITICAL
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
     * @param SalesChannelContext $salesChannelContext
     * @return Payment
     */
    public function createOrReusePayment(string $mollieOrderId, string $paymentMethod, SalesChannelContext $salesChannelContext): Payment
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelContext->getSalesChannel()->getId(), $salesChannelContext->getContext(), ['embed' => 'payments']);

        if (!$mollieOrder instanceof MollieOrder) {

            throw new MollieOrderCouldNotBeFetchedException($mollieOrderId);
        }

        $payment = $this->getOpenPayment($mollieOrder);

        if (!$payment instanceof Payment) {
            $this->logger->addDebugEntry(
                'Didn\'t find an open payment. Creating a new payment at mollie',
                $salesChannelContext->getSalesChannel()->getId(),
                $salesChannelContext->getContext()
            );

            return $mollieOrder->createPayment(['method' => $paymentMethod]);
        }

        if ($payment->method === $paymentMethod) {
            $this->logger->addDebugEntry(
                'Found an open payment and payment methods are same. Reusing this payment',
                $salesChannelContext->getSalesChannel()->getId(),
                $salesChannelContext->getContext()
            );

            return $payment;
        }

        if (!$payment->isCancelable) {
            $this->logger->addDebugEntry(
                'Found an open payment but it isn\'t cancelable. Reusing this payment, otherwise we could never complete payment',
                $salesChannelContext->getSalesChannel()->getId(),
                $salesChannelContext->getContext()
            );

            return $payment;
        }

        try {
            $this->logger->addDebugEntry(
                'Found an open payment and cancel it. Create new payment with new payment method',
                $salesChannelContext->getSalesChannel()->getId(),
                $salesChannelContext->getContext()
            );

            $this->paymentApiService->delete($payment->id, $salesChannelContext->getSalesChannel()->getId());

            /** @var Payment $payment */
            $payment = $mollieOrder->createPayment(['method' => $paymentMethod]);

            return $payment;
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

    public function getPaymentUrl(string $mollieOrderId, string $salesChannelId, Context $context): ?string
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId, $context);

        return $mollieOrder->status === 'created' ? $mollieOrder->getCheckoutUrl() : null;
    }

    public function setShipment(string $mollieOrderId, string $salesChannelId, Context $context): bool
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId, $context);

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
     * @param Context $context
     * @return bool
     * @throws CouldNotFetchMollieOrderException
     */
    public function isCompletelyShipped(string $mollieOrderId, string $salesChannelId, Context $context): bool
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId, $context);

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
     * @param Context $context
     * @return Payment
     */
    public function getCompletedPayment(string $mollieOrderId, ?string $salesChannelId, Context $context): Payment
    {
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelId, $context, ['embed' => 'payments']);

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
