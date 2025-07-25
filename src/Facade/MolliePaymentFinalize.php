<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Exception\MissingMollieOrderIdException;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentDetails;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Shopware\Component\Transaction\PaymentTransactionStruct;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class MolliePaymentFinalize
{
    private const FLOWBUILDER_SUCCESS = 'success';
    private const FLOWBUILDER_FAILED = 'failed';
    private const FLOWBUILDER_CANCELED = 'canceled';

    /**
     * @var OrderStatusConverter
     */
    private $orderStatusConverter;
    /**
     * @var OrderStatusUpdater
     */
    private $orderStatusUpdater;
    /**
     * @var SettingsService
     */
    private $settingsService;
    /**
     * @var Order
     */
    private $mollieOrderService;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var EntityRepository<EntityCollection<CustomerEntity>>
     */
    private $repoCustomer;

    /**
     * @var FlowBuilderFactory
     */
    private $flowBuilderFactory;

    /**
     * @var FlowBuilderEventFactory
     */
    private $flowBuilderEventFactory;

    /**
     * @param EntityRepository<EntityCollection<CustomerEntity>> $repoCustomer
     */
    public function __construct(OrderStatusConverter $orderStatusConverter, OrderStatusUpdater $orderStatusUpdater, SettingsService $settingsService, Order $mollieOrderService, OrderService $orderService, SubscriptionManager $subscriptionManager, $repoCustomer, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory)
    {
        $this->orderStatusConverter = $orderStatusConverter;
        $this->orderStatusUpdater = $orderStatusUpdater;
        $this->settingsService = $settingsService;
        $this->mollieOrderService = $mollieOrderService;
        $this->orderService = $orderService;
        $this->subscriptionManager = $subscriptionManager;
        $this->repoCustomer = $repoCustomer;
        $this->flowBuilderFactory = $flowBuilderFactory;
        $this->flowBuilderEventFactory = $flowBuilderEventFactory;
    }

    /**
     * @throws \Exception
     */
    public function finalize(PaymentTransactionStruct $transactionStruct, Context $context, string $salesChannelId): void
    {
        $order = $transactionStruct->getOrder();

        $customFields = $order->getCustomFields() ?? [];
        $customFieldsStruct = new OrderAttributes($order);
        $mollieOrderId = $customFieldsStruct->getMollieOrderId();

        if (empty($mollieOrderId)) {
            $orderNumber = $order->getOrderNumber() ?? '-';

            throw new MissingMollieOrderIdException($orderNumber);
        }

        $mollieOrder = $this->mollieOrderService->getMollieOrder(
            $mollieOrderId,
            $salesChannelId,
            ['embed' => 'payments']
        );

        $settings = $this->settingsService->getSettings($salesChannelId);
        $paymentStatus = $this->orderStatusConverter->getMollieOrderStatus($mollieOrder);

        // Attention
        // Our payment status will either be set by us, or automatically by Shopware using exceptions below.
        // But the order status, is something that we always have to set MANUALLY in both cases.
        // That's why we do this here, before throwing exceptions.
        $this->orderStatusUpdater->updateOrderStatus(
            $order,
            $paymentStatus,
            $settings,
            $context
        );

        $paymentMethod = $transactionStruct->getOrderTransaction()->getPaymentMethod();

        // in some combinations (older Shopware versions + Mollie failure mode)
        // we don't have a payment method in the order transaction.
        // so we grab our identifier from the mollie order
        if ($paymentMethod instanceof PaymentMethodEntity) {
            // load our correct key
            // from the shopware payment method custom field
            $mollieAttributes = new PaymentMethodAttributes($paymentMethod);
            $molliePaymentMethodKey = $mollieAttributes->getMollieIdentifier();
        } else {
            // load it from the mollie order id
            $molliePaymentMethodKey = $mollieOrder->method;
        }

        // now either set the payment status for successful payments
        // or make sure to throw an exception for Shopware in case of failed payments.
        if (MolliePaymentStatus::isFailedStatus($molliePaymentMethodKey, $paymentStatus)) {
            $orderTransactionID = $transactionStruct->getOrderTransaction()->getUniqueIdentifier();

            // let's also create a different handling, if the customer either cancelled
            // or if the payment really failed. this will lead to a different order payment status in the end.
            if ($paymentStatus === MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED) {
                $message = sprintf('Payment for order %s (%s) was cancelled by the customer.', $order->getOrderNumber(), $mollieOrder->id);

                // fire flow builder event
                $this->fireFlowBuilderEvent(self::FLOWBUILDER_CANCELED, $order, $context);

                throw PaymentException::customerCanceled($orderTransactionID, $message);
            }
            $message = sprintf('Payment for order %s (%s) failed. The Mollie payment status was not successful for this payment attempt.', $order->getOrderNumber(), $mollieOrder->id);

            // fire flow builder event
            $this->fireFlowBuilderEvent(self::FLOWBUILDER_FAILED, $order, $context);

            throw PaymentException::asyncFinalizeInterrupted($orderTransactionID, $message);
        }

        // --------------------------------------------------------------------------------------------------------------------

        $this->orderStatusUpdater->updatePaymentStatus($transactionStruct->getOrderTransaction(), $paymentStatus, $context);

        // now update the custom fields of the order
        // we want to have as much information as possible in the shopware order
        // this includes the Mollie Payment ID and maybe additional references
        $this->orderService->updateMollieDataCustomFields(
            $order,
            $mollieOrderId,
            '',
            $transactionStruct->getOrderTransactionId(),
            0,
            $context
        );

        // --------------------------------------------------------------------------------------------------------------------
        // attention this is indeed a "hack".
        // we don't have real webhooks in our cypress pipeline tests.
        // this means the real subscription-handshake cannot be done.
        // but we still need a mollie subscription. so there is a hidden cypress ENV mode.
        // if enabled, we immediately create a subscription in this RETURN url instead of the webhook
        $orderAttributes = new OrderAttributes($order);

        if ($this->settingsService->getMollieCypressMode() && $orderAttributes->isTypeSubscription()) {
            if ($mollieOrder->payments() !== null && count($mollieOrder->payments()) > 0) {
                $paymentDetails = new MolliePaymentDetails();
                $lasMolliePayment = count($mollieOrder->payments()) - 1;
                $mandateId = $paymentDetails->getMandateId($mollieOrder->payments()[$lasMolliePayment]);
                $this->subscriptionManager->confirmSubscription($order, $mandateId, $context);
            }
        }

        // --------------------------------------------------------------------------------------------------------------------
        // FLOW BUILDER

        $this->fireFlowBuilderEvent(self::FLOWBUILDER_SUCCESS, $order, $context);
    }

    /**
     * @throws \Exception
     */
    private function fireFlowBuilderEvent(string $status, OrderEntity $order, Context $context): void
    {
        $orderCustomer = $order->getOrderCustomer();

        if (! $orderCustomer instanceof OrderCustomerEntity) {
            return;
        }

        $criteria = new Criteria([(string) $orderCustomer->getCustomerId()]);

        $customers = $this->repoCustomer->search($criteria, $context);

        if ($customers->count() <= 0) {
            return;
        }
        /** @var ?CustomerEntity $customer */
        $customer = $customers->first();
        if ($customer === null) {
            return;
        }
        // we also have to reload the order because data is missing
        $finalOrder = $this->orderService->getOrder($order->getId(), $context);

        switch ($status) {
            case self::FLOWBUILDER_FAILED:
                $event = $this->flowBuilderEventFactory->buildOrderFailedEvent($customer, $finalOrder, $context);
                break;

            case self::FLOWBUILDER_CANCELED:
                $event = $this->flowBuilderEventFactory->buildOrderCanceledEvent($customer, $finalOrder, $context);
                break;

            default:
                $event = $this->flowBuilderEventFactory->buildOrderSuccessEvent($customer, $finalOrder, $context);
        }

        $this->flowBuilderFactory->createDispatcher()->dispatch($event);
    }
}
