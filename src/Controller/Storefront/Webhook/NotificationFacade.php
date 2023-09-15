<?php

namespace Kiener\MolliePayments\Controller\Storefront\Webhook;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\SubscriptionManager;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Repository\OrderTransaction\OrderTransactionRepositoryInterface;
use Kiener\MolliePayments\Repository\PaymentMethod\PaymentMethodRepositoryInterface;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentDetails;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Api\Resources\Order;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class NotificationFacade
{
    /**
     * @var MollieGatewayInterface
     */
    private $gatewayMollie;

    /**
     * @var OrderStatusConverter
     */
    private $statusConverter;

    /**
     * @var OrderStatusUpdater
     */
    private $statusUpdater;

    /**
     * @var PaymentMethodRepositoryInterface
     */
    private $repoPaymentMethods;

    /**
     * @var OrderTransactionRepositoryInterface
     */
    private $repoOrderTransactions;

    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $flowBuilderDispatcher;

    /**
     * @var FlowBuilderEventFactory
     */
    private $flowBuilderEventFactory;

    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var MolliePaymentDetails
     */
    private $molliePaymentDetails;

    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SettingsService
     */
    private $settingsService;


    /**
     * @param MollieGatewayInterface $gatewayMollie
     * @param OrderStatusConverter $statusConverter
     * @param OrderStatusUpdater $statusUpdater
     * @param PaymentMethodRepositoryInterface $repoPaymentMethods
     * @param OrderTransactionRepositoryInterface $repoOrderTransactions
     * @param FlowBuilderFactory $flowBuilderFactory
     * @param FlowBuilderEventFactory $flowBuilderEventFactory
     * @param SettingsService $serviceService
     * @param SubscriptionManager $subscription
     * @param OrderService $orderService
     * @param LoggerInterface $logger
     * @throws \Exception
     */
    public function __construct(MollieGatewayInterface $gatewayMollie, OrderStatusConverter $statusConverter, OrderStatusUpdater $statusUpdater, PaymentMethodRepositoryInterface $repoPaymentMethods, OrderTransactionRepositoryInterface $repoOrderTransactions, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, SettingsService $serviceService, SubscriptionManager $subscription, OrderService $orderService, LoggerInterface $logger)
    {
        $this->gatewayMollie = $gatewayMollie;
        $this->statusConverter = $statusConverter;
        $this->statusUpdater = $statusUpdater;
        $this->repoPaymentMethods = $repoPaymentMethods;
        $this->repoOrderTransactions = $repoOrderTransactions;
        $this->flowBuilderEventFactory = $flowBuilderEventFactory;
        $this->subscriptionManager = $subscription;
        $this->settingsService = $serviceService;
        $this->orderService = $orderService;
        $this->logger = $logger;

        $this->molliePaymentDetails = new MolliePaymentDetails();

        $this->flowBuilderDispatcher = $flowBuilderFactory->createDispatcher();
    }


    /**
     * @param string $swTransactionId
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     * @return void
     */
    public function onNotify(string $swTransactionId, Context $context): void
    {
        # -----------------------------------------------------------------------------------------------------
        # LOAD TRANSACTION
        $swTransaction = $this->getTransaction($swTransactionId, $context);

        if (!$swTransaction instanceof OrderTransactionEntity) {
            throw new \Exception('Transaction ' . $swTransactionId . ' not found in Shopware');
        }

        # -----------------------------------------------------------------------------------------------------

        $swOrder = $swTransaction->getOrder();

        if (!$swOrder instanceof OrderEntity) {
            throw new OrderNotFoundException('Shopware Order not found for transaction: ' . $swTransactionId);
        }

        # --------------------------------------------------------------------------------------------

        # now get the correct settings from the sales channel of that order.
        # our order might be from a different sales channel, or even a headless sales channel
        $settings = $this->settingsService->getSettings($swOrder->getSalesChannelId());

        # also set the gateway to our correct sales channel API key
        $this->gatewayMollie->switchClient($swOrder->getSalesChannelId());

        # --------------------------------------------------------------------------------------------

        # now get the latest transaction of that order
        # we always need to make sure to use the latest one, because this
        # is the one, that is really visible in the administration.
        # if we don't add to that one, then the previous one is suddenly visible again
        # which causes confusion and troubles in the end
        $swTransaction = $this->getOrderTransactions($swOrder->getId(), $context)->last();


        # verify if the customer really paid with Mollie in the end
        $paymentMethod = $swTransaction->getPaymentMethod();
        $paymentMethodAttributes = new PaymentMethodAttributes($paymentMethod);

        if (!$paymentMethodAttributes->isMolliePayment()) {
            # just skip it if it has been paid
            # with another payment provider
            # do NOT throw an error
            return;
        }


        # --------------------------------------------------------------------------------------------

        $orderAttributes = new OrderAttributes($swOrder);

        $mollieOrderId = $orderAttributes->getMollieOrderId();
        $molliePaymentId = $orderAttributes->getMolliePaymentId();

        $this->gatewayMollie->switchClient($swOrder->getSalesChannelId());

        $molliePayment = null;
        $mollieOrder = null;

        if (!empty($orderAttributes->getMollieOrderId())) {
            # fetch the order of our mollie ID
            # from our sales channel mollie profile
            $mollieOrder = $this->gatewayMollie->getOrder($mollieOrderId);
            $molliePayment = $this->statusConverter->getLatestPayment($mollieOrder);
            $status = $this->statusConverter->getMollieOrderStatus($mollieOrder);
        } elseif ($orderAttributes->isTypeSubscription()) {
            # subscriptions are automatically charged using a payment ID
            # so we do not have an order, but a payment instead
            $molliePayment = $this->gatewayMollie->getPayment($molliePaymentId);
            $status = $this->statusConverter->getMolliePaymentStatus($molliePayment);
        } else {
            throw new \Exception('Order is neither a Mollie order nor a subscription order: ' . $swOrder->getOrderNumber());
        }

        # --------------------------------------------------------------------------------------------


        $logId = (!empty($mollieOrderId)) ? $mollieOrderId : $molliePaymentId;
        $this->logger->info('Webhook for order ' . $swOrder->getOrderNumber() . ' and Mollie ID: ' . $logId . ' has been received with Status: ' . $status);


        $this->statusUpdater->updatePaymentStatus($swTransaction, $status, $context);

        $this->statusUpdater->updateOrderStatus($swOrder, $status, $settings, $context);

        # --------------------------------------------------------------------------------------------

        # let's check what payment method has been used
        # if somehow the user switched to a different one
        # then we want to update the one inside Shopware
        # If our order is Apple Pay, then DO NOT CONVERT THIS TO CREDIT CARD!
        # we want to keep Apple Pay as payment method
        if ($swTransaction->getPaymentMethod() instanceof PaymentMethodEntity && $mollieOrder instanceof Order && $swTransaction->getPaymentMethod() instanceof PaymentMethodEntity && $swTransaction->getPaymentMethod()->getHandlerIdentifier() !== ApplePayPayment::class) {
            $this->updatePaymentMethod($swTransaction, $mollieOrder, $context);
        }

        # --------------------------------------------------------------------------------------------
        # SUBSCRIPTION
        # this will confirm our created subscriptions in all cases of successful payments.
        # that path will create the actual subscription inside Mollie which will be used for recurring.
        # if our payment expired, then we can also expire our local subscription in the database.

        switch ($status) {
            case MolliePaymentStatus::MOLLIE_PAYMENT_PAID:
            case MolliePaymentStatus::MOLLIE_PAYMENT_PENDING:
            case MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                # it is very important that we use the mandate from this payment
                # for the subscription, because a customer could have different mandates!
                # keep in mind, this might be empty here...our confirm endpoint does the final checks
                $mandateId = $this->molliePaymentDetails->getMandateId($molliePayment);
                $this->subscriptionManager->confirmSubscription($swOrder, $mandateId, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $this->subscriptionManager->cancelPendingSubscriptions($swOrder, $context);
                break;
        }

        # now update the custom fields of the order
        # we want to have as much information as possible in the shopware order
        # this includes the Mollie Payment ID and maybe additional references
        $this->orderService->updateMollieDataCustomFields(
            $swOrder,
            $mollieOrderId,
            $molliePaymentId,
            $swTransaction->getId(),
            $context
        );

        # --------------------------------------------------------------------------------------------
        # FIRE FLOW BUILDER TRIGGER EVENT
        # we have an adapter setup anyway here, so if flow builder is
        # not yet supported in this shopware version, then
        # this only triggers a dummy dispatcher ;)
        $this->fireFlowBuilderEvents($swOrder, $status, $context);
    }


    /**
     * @param string $transactionId
     * @param Context $context
     * @return null|OrderTransactionEntity
     */
    private function getTransaction(string $transactionId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $transactionId));
        $criteria->addAssociation('order');
        $criteria->addAssociation('order.salesChannel');
        $criteria->addAssociation('order.lineItems');
        $criteria->addAssociation('order.currency');
        $criteria->addAssociation('order.transactions');
        $criteria->addAssociation('paymentMethod');

        return $this->repoOrderTransactions->search($criteria, $context)->first();
    }

    /**
     * @param string $orderID
     * @param Context $context
     * @return EntitySearchResult<OrderTransactionEntity>
     */
    public function getOrderTransactions(string $orderID, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $orderID));
        $criteria->addAssociation('order');
        $criteria->addAssociation('paymentMethod');
        $criteria->addSorting(new FieldSorting('createdAt'));

        /** @var EntitySearchResult<OrderTransactionEntity> $result */
        $result = $this->repoOrderTransactions->search($criteria, $context);

        return $result;
    }


    /**
     * @param OrderTransactionEntity $transaction
     * @param Order $mollieOrder
     * @param Context $context
     */
    private function updatePaymentMethod(OrderTransactionEntity $transaction, Order $mollieOrder, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(
                'AND',
                [
                    new ContainsFilter('handlerIdentifier', 'Kiener\MolliePayments\Handler\Method'),
                    new EqualsFilter('customFields.mollie_payment_method_name', $mollieOrder->method)
                ]
            )
        );

        $shopwarePaymentId = $this->repoPaymentMethods->searchIds($criteria, $context)->firstId();

        if (is_null($shopwarePaymentId)) {
            # if the payment method is not available locally in shopware
            # then we just skip the update process
            # we do not want to fail our notification because of this
            return;
        }

        $transaction->setPaymentMethodId($shopwarePaymentId);

        $this->repoOrderTransactions->update(
            [
                [
                    'id' => $transaction->getUniqueIdentifier(),
                    'paymentMethodId' => $shopwarePaymentId
                ]
            ],
            $context
        );
    }

    /**
     * @param OrderEntity $swOrder
     * @param string $status
     * @param Context $context
     * @return void
     */
    private function fireFlowBuilderEvents(OrderEntity $swOrder, string $status, Context $context): void
    {
        $this->flowBuilderDispatcher->dispatch(
            $this->flowBuilderEventFactory->buildWebhookReceivedAll($swOrder, $status, $context)
        );

        $paymentEvent = null;

        switch ($status) {
            case MolliePaymentStatus::MOLLIE_PAYMENT_FAILED:
                $paymentEvent = $this->flowBuilderEventFactory->buildWebhookReceivedFailedEvent($swOrder, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_CANCELED:
                $paymentEvent = $this->flowBuilderEventFactory->buildWebhookReceivedCancelledEvent($swOrder, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_EXPIRED:
                $paymentEvent = $this->flowBuilderEventFactory->buildWebhookReceivedExpiredEvent($swOrder, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PENDING:
                $paymentEvent = $this->flowBuilderEventFactory->buildWebhookReceivedPendingEvent($swOrder, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_AUTHORIZED:
                $paymentEvent = $this->flowBuilderEventFactory->buildWebhookReceivedAuthorizedEvent($swOrder, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PAID:
                $paymentEvent = $this->flowBuilderEventFactory->buildWebhookReceivedPaidEvent($swOrder, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_COMPLETED:
                $paymentEvent = $this->flowBuilderEventFactory->buildWebhookReceivedCompletedEvent($swOrder, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_CHARGEBACK:
                $paymentEvent = $this->flowBuilderEventFactory->buildWebhookReceivedChargebackEvent($swOrder, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_REFUNDED:
                $paymentEvent = $this->flowBuilderEventFactory->buildWebhookReceivedRefundedEvent($swOrder, $context);
                break;

            case MolliePaymentStatus::MOLLIE_PAYMENT_PARTIALLY_REFUNDED:
                $paymentEvent = $this->flowBuilderEventFactory->buildWebhookReceivedPartialRefundedEvent($swOrder, $context);
                break;
        }

        if ($paymentEvent !== null) {
            $this->flowBuilderDispatcher->dispatch($paymentEvent);
        }
    }
}
