<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Exception\MollieOrderCancelledException;
use Kiener\MolliePayments\Exception\MollieOrderExpiredException;
use Kiener\MolliePayments\Exception\PaymentNotFoundException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Handler\Method\CreditCardPayment;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\MollieApi\Payment as MolliePayment;
use Kiener\MolliePayments\Service\MollieApi\Payment as PaymentApiService;
use Kiener\MolliePayments\Service\MollieApi\RequestAnonymizer\MollieRequestAnonymizer;
use Kiener\MolliePayments\Service\Router\RoutingBuilder;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Order as MollieOrder;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;
use Mollie\Api\Types\OrderLineType;
use Mollie\Api\Types\OrderStatus;
use Mollie\Api\Types\PaymentStatus;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
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
     * @var RoutingBuilder
     */
    private $routingBuilder;

    /**
     * @var MollieRequestAnonymizer
     */
    private $requestAnonymizer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @param MollieApiFactory $clientFactory
     * @param MolliePayment $paymentApiService
     * @param RoutingBuilder $routingBuilder
     * @param MollieRequestAnonymizer $requestAnonymizer
     * @param LoggerInterface $logger
     * @param SettingsService $settingsService
     */
    public function __construct(MollieApiFactory $clientFactory, PaymentApiService $paymentApiService, RoutingBuilder $routingBuilder, MollieRequestAnonymizer $requestAnonymizer, LoggerInterface $logger, SettingsService $settingsService)
    {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
        $this->paymentApiService = $paymentApiService;
        $this->routingBuilder = $routingBuilder;
        $this->requestAnonymizer = $requestAnonymizer;
        $this->settingsService = $settingsService;
    }

    /**
     * @param string $mollieOrderId
     * @param string $salesChannelId
     * @param array<mixed> $parameters
     * @return MollieOrder
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
     * @param string $paymentId
     * @param string $salesChannelId
     * @param array<mixed> $parameters
     * @return Payment
     */
    public function getMolliePayment(string $paymentId, string $salesChannelId, array $parameters = []): Payment
    {
        try {
            $apiClient = $this->clientFactory->getClient($salesChannelId);

            return $apiClient->payments->get($paymentId, $parameters);
        } catch (ApiException $e) {
            $this->logger->error(
                sprintf(
                    'API error occurred when fetching mollie payment %s with message %s',
                    $paymentId,
                    $e->getMessage()
                )
            );

            throw new CouldNotFetchMollieOrderException($paymentId, $e);
        }
    }

    /**
     * @param string $mollieOrderId
     * @param string $mollieOrderLineId
     * @param string $salesChannelId
     * @throws \Exception
     * @return OrderLine
     */
    public function getMollieOrderLine(string $mollieOrderId, string $mollieOrderLineId, string $salesChannelId): OrderLine
    {
        $order = $this->getMollieOrder($mollieOrderId, $salesChannelId);

        $orderLine = $order->lines()->get($mollieOrderLineId);

        if (!$orderLine instanceof OrderLine) {
            throw new \Exception('No order line found for mollie order ' . $mollieOrderId);
        }

        return $orderLine;
    }

    /**
     * @param array<mixed> $orderData
     * @param string $orderSalesChannelContextId
     * @param SalesChannelContext $salesChannelContext
     * @return MollieOrder
     */
    public function createOrder(array $orderData, string $orderSalesChannelContextId, SalesChannelContext $salesChannelContext): MollieOrder
    {
        $apiClient = $this->clientFactory->getClient($orderSalesChannelContextId);

        /**
         * Create an order at Mollie based on the prepared array of order data.
         */
        try {
            $anonymizedData = $this->requestAnonymizer->anonymize($orderData);

            $this->logger->debug(
                'Mollie Order Request',
                [
                    'body' => $anonymizedData
                ]
            );

            return $apiClient->orders->create($orderData);
        } catch (ApiException $e) {
            $this->logger->critical(
                'Could not create Mollie order',
                [
                    'function' => 'finalize-payment',
                    'exception' => $e
                ]
            );

            throw new RuntimeException('Could not create Mollie order', $e->getCode(), $e);
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
     * @throws ApiException
     * @return Payment
     */
    public function createOrReusePayment(string $mollieOrderId, string $paymentMethod, string $swOrderTransactionID, PaymentHandler $paymentHandler, OrderEntity $order, CustomerEntity $customer, SalesChannelContext $salesChannelContext): Payment
    {
        # fetch the current Mollie order including
        # all its existing payments and transactions
        $mollieOrder = $this->getMollieOrder($mollieOrderId, $salesChannelContext->getSalesChannel()->getId(), ['embed' => 'payments']);

        # We cannot reuse this order if it's cancelled or expired.
        switch ($mollieOrder->status) {
            case OrderStatus::STATUS_CANCELED:
                throw new MollieOrderCancelledException($mollieOrderId);
            case OrderStatus::STATUS_EXPIRED:
                throw new MollieOrderExpiredException($mollieOrderId);
        }

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
                $mollieOrder,
                $paymentMethod,
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
     * @return null|Payment
     */
    public function getPaidPayment(MollieOrder $mollieOrder): ?Payment
    {
        $payments = $mollieOrder->payments();

        if (!$payments instanceof PaymentCollection) {
            return null;
        }

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($payment->isPaid()) {
                return $payment;
            }
        }

        return null;
    }

    /**
     * @param MollieOrder $mollieOrder
     * @return null|Payment
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
     * @param MollieOrder $mollieOrder
     * @param string $paymentMethod
     * @param string $swOrderTransactionID
     * @param PaymentHandler $paymentHandler
     * @param OrderEntity $order
     * @param CustomerEntity $customer
     * @param SalesChannelContext $salesChannelContext
     * @throws ApiException
     * @return Payment
     */
    private function createNewOrderPayment(MollieOrder $mollieOrder, string $paymentMethod, string $swOrderTransactionID, PaymentHandler $paymentHandler, OrderEntity $order, CustomerEntity $customer, SalesChannelContext $salesChannelContext): Payment
    {
        $webhookUrl = $this->routingBuilder->buildWebhookURL($swOrderTransactionID);


        # let's create a new order body
        # for our new payment.
        $newPaymentData = [
            'payment' => [
                'method' => $paymentMethod,
            ],
        ];

        $settings = $this->settingsService->getSettings($order->getSalesChannelId());
        # set CreditCardPayment singleClickPayment true if Single click payment feature is enabled
        if ($paymentHandler instanceof CreditCardPayment && $settings->isOneClickPaymentsEnabled()) {
            $paymentHandler->setEnableSingleClickPayment(true);
        }

        # now we have to add payment specific data
        # like we would do with initial orders too
        $tmpOrder = $paymentHandler->processPaymentMethodSpecificParameters($newPaymentData, $order, $salesChannelContext, $customer);
        # extract our modified and final payment data
        $finalPaymentData = $tmpOrder['payment'];


        # create our new payment with the
        # Mollie API for our existing order
        /** @var Payment $payment */
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
     * @throws CouldNotFetchMollieOrderException
     * @return bool
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
     * @param string $molliePaymentId
     * @param null|string $salesChannelId
     * @return Payment
     */
    public function getCompletedPayment(string $mollieOrderId, string $molliePaymentId, ?string $salesChannelId): Payment
    {
        $allowed = [
            PaymentStatus::STATUS_PAID,
            PaymentStatus::STATUS_AUTHORIZED // Klarna
        ];


        if (!empty($mollieOrderId)) {
            # ORDER_ID, ord_123
            $mollieOrder = $this->getMollieOrder($mollieOrderId, (string)$salesChannelId, ['embed' => 'payments']);

            $payments = $mollieOrder->payments();

            if ($payments instanceof PaymentCollection) {
                if ($payments->count() === 0) {
                    throw new PaymentNotFoundException($mollieOrderId);
                }

                foreach ($payments->getArrayCopy() as $payment) {
                    if (in_array($payment->status, $allowed)) {
                        return $payment;
                    }
                }
            }

            throw new PaymentNotFoundException($mollieOrderId);
        } else {
            # TRANSACTION_ID,.... tr_abc

            $payment = $this->getMolliePayment($molliePaymentId, (string)$salesChannelId);

            if (in_array($payment->status, $allowed)) {
                return $payment;
            }

            throw new PaymentNotFoundException($molliePaymentId);
        }
    }
}
