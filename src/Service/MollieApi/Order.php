<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\MollieApi\Payment as PaymentApiService;
use Kiener\MolliePayments\Service\WebhookBuilder\WebhookBuilder;
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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

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
     * @var WebhookBuilder
     */
    private $webhookBuilder;

    /**
     * @var LoggerInterface
     */
    private $logger;



    public function __construct(MollieApiFactory $clientFactory, PaymentApiService $paymentApiService, RouterInterface $router, LoggerInterface $logger)
    {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
        $this->paymentApiService = $paymentApiService;

        $this->webhookBuilder = new WebhookBuilder($router);
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
        string $mollieOrderId,
        string $mollieOrderLineId,
        string $salesChannelId
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
     * @param string $mollieOrderId
     * @param string $paymentMethod
     * @param string $swOrderTransactionID
     * @param PaymentHandler $paymentHandler
     * @param OrderEntity $order
     * @param CustomerEntity $customer
     * @param SalesChannelContext $salesChannelContext
     * @return Payment
     * @throws ApiException
     */
    public function createOrReusePayment(string $mollieOrderId, string $paymentMethod, string $swOrderTransactionID, PaymentHandler $paymentHandler, OrderEntity $order, CustomerEntity $customer, SalesChannelContext $salesChannelContext): Payment
    {
        # fetch the current Mollie order including
        # all its existing payments and transactions
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelContext->getSalesChannel()->getId(), ['embed' => 'payments']);

        # now search for an open payment
        # if it's still open, then we just reuse this one
        $existingOpenPayment = $this->getOpenPayment($mollieOrder);


        # it's not possible to have more than 1 payment OPEN
        # also, OPEN payments cannot be cancelled.
        # there are circumstances where we retry even though a payment is open.
        # this could be a navigation to the orders in the account where a change-payment method is possible.
        # so if we have no OPEN payment, we just create a new one
        # if we have one, we make sure to update this one

        if (!$existingOpenPayment instanceof Payment) {
            return $this->createNewOrderPayment(
                $mollieOrder, $paymentMethod,
                $swOrderTransactionID,
                $paymentHandler,
                $order,
                $customer,
                $salesChannelContext
            );
        }

        # -------------------------------------------------------------------------------------------------------

        # verify if we can cancel the previous payment.
        # if we can, better to these, just to have some nice and clean data.
        # we will then create a new payment for our attempt.
        if ($existingOpenPayment->isCancelable) {

            $this->paymentApiService->delete(
                $existingOpenPayment->id,
                $salesChannelContext->getSalesChannel()->getId()
            );

            return $this->createNewOrderPayment(
                $mollieOrder,
                $paymentMethod,
                $swOrderTransactionID,
                $paymentHandler,
                $order,
                $customer,
                $salesChannelContext
            );
        }


        # TODO does not yet work and I'm not quite sure if that is even happening?!
        # we have to update the payment method, if it switches.
        # otherwise one would still see the previous one
        # $existingOpenPayment = $this->updateExistingPayment(
        #     $existingOpenPayment,
        #     $paymentMethod,
        #     $salesChannelContext->getSalesChannelId()
        # );

        return $existingOpenPayment;
    }


    /**
     * @param MollieOrder $mollieOrder
     * @return Payment|null
     */
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
     * @param Payment $payment
     * @param string $newPaymnetMethod
     * @param string $salesChannelID
     * @return Payment
     * @throws ApiException
     */
    private function updateExistingPayment(Payment $payment, string $newPaymnetMethod, string $salesChannelID): Payment
    {
        #  NOT WORKING ? !damn
        # but we need to update the payment method :D TODO
        $apiClient = $this->clientFactory->getClient($salesChannelID);

        return $apiClient->payments->update(
            $payment->id,
            [
                'method' => $newPaymnetMethod,
            ]
        );
    }

    /**
     * @param MollieOrder $mollieOrder
     * @param string $paymentMethod
     * @param string $swOrderTransactionID
     * @param PaymentHandler $paymentHandler
     * @param OrderEntity $order
     * @param CustomerEntity $customer
     * @param SalesChannelContext $salesChannelContext
     * @return Payment
     * @throws ApiException
     */
    private function createNewOrderPayment(MollieOrder $mollieOrder, string $paymentMethod, string $swOrderTransactionID, PaymentHandler $paymentHandler, OrderEntity $order, CustomerEntity $customer, SalesChannelContext $salesChannelContext): Payment
    {
        $webhookUrl = $this->webhookBuilder->buildWebhook($swOrderTransactionID);


        # let's create a new order body
        # for our new payment.
        $newPaymentData = [
            'payment' => [
                'method' => $paymentMethod,
            ],
        ];

        # now we have to add payment specific data
        # like we would do with initial orders too
        $tmpOrder = $paymentHandler->processPaymentMethodSpecificParameters($newPaymentData, $order, $salesChannelContext, $customer);
        # extract our modified and final payment data
        $finalPaymentData = $tmpOrder['payment'];


        # create our new payment with the
        # Mollie API for our existing order
        $payment = $mollieOrder->createPayment($finalPaymentData);

        # unfortunately the API has a bug at the moment.
        # we cannot modify the webhook URL with the create method.
        # but we need to make sure to change it to the new OrderTransactionID of Shopware
        $apiClient = $this->clientFactory->getClient($salesChannelContext->getSalesChannelId());

        $apiClient->payments->update(
            $payment->id,
            [
                'webhookUrl' => $webhookUrl,
            ]
        );

        return $payment;
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
