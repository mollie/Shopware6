<?php

namespace Kiener\MolliePayments\Facade\Notifications;


use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Handler\Method\ApplePayPayment;
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Kiener\MolliePayments\Service\Mollie\OrderStatusConverter;
use Kiener\MolliePayments\Service\Order\OrderStatusUpdater;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Struct\PaymentMethod\PaymentMethodAttributes;
use Mollie\Api\Resources\Order;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;


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
     * @var EntityRepositoryInterface
     */
    private $repoPaymentMethods;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoOrderTransactions;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $flowBuilderDispatcher;

    /**
     * @var FlowBuilderEventFactory
     */
    private $flowBuilderEventFactory;


    /**
     * @param MollieGatewayInterface $gatewayMollie
     * @param OrderStatusConverter $statusConverter
     * @param OrderStatusUpdater $statusUpdater
     * @param EntityRepositoryInterface $repoPaymentMethods
     * @param EntityRepositoryInterface $repoOrderTransactions
     * @param FlowBuilderFactory $flowBuilderFactory
     * @param FlowBuilderEventFactory $flowBuilderEventFactory
     * @param LoggerInterface $logger
     * @throws \Exception
     */
    public function __construct(MollieGatewayInterface $gatewayMollie, OrderStatusConverter $statusConverter, OrderStatusUpdater $statusUpdater, EntityRepositoryInterface $repoPaymentMethods, EntityRepositoryInterface $repoOrderTransactions, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, LoggerInterface $logger)
    {
        $this->gatewayMollie = $gatewayMollie;
        $this->statusConverter = $statusConverter;
        $this->statusUpdater = $statusUpdater;
        $this->repoPaymentMethods = $repoPaymentMethods;
        $this->repoOrderTransactions = $repoOrderTransactions;
        $this->flowBuilderEventFactory = $flowBuilderEventFactory;
        $this->logger = $logger;

        $this->flowBuilderDispatcher = $flowBuilderFactory->createDispatcher();
    }


    /**
     * @param string $transactionId
     * @param MollieSettingStruct $settings
     * @param SalesChannelContext $contextSC
     * @throws \Exception
     */
    public function onNotify(string $transactionId, MollieSettingStruct $settings, SalesChannelContext $contextSC): void
    {
        # -----------------------------------------------------------------------------------------------------
        # LOAD TRANSACTION
        $transaction = $this->getTransaction($transactionId, $contextSC->getContext());

        if (!$transaction instanceof OrderTransactionEntity) {
            throw new \Exception('Transaction ' . $transactionId . ' not found in Shopware');
        }

        # -----------------------------------------------------------------------------------------------------

        $swOrder = $transaction->getOrder();

        if (!$swOrder instanceof OrderEntity) {
            throw new OrderNotFoundException('Shopware Order not found for transaction: ' . $transactionId);
        }

        $mollieOrderId = $this->getMollieId($swOrder);

        # now get the latest transaction of that order
        # we always need to make sure to use the latest one, because this
        # is the one, that is really visible in the administration.
        # if we don't add to that one, then the previous one is suddenly visible again
        # which causes confusion and troubles in the end
        $transaction = $this->getOrderTransactions($swOrder->getId(), $contextSC->getContext())->last();


        # verify if the customer really paid with Mollie in the end
        $paymentMethod = $transaction->getPaymentMethod();
        $paymentMethodAttributes = new PaymentMethodAttributes($paymentMethod);

        if (!$paymentMethodAttributes->isMolliePayment()) {
            # just skip it if it has been paid
            # with another payment provider
            # do NOT throw an error
            return;
        }


        # --------------------------------------------------------------------------------------------

        $this->gatewayMollie->switchClient($contextSC->getSalesChannel()->getId());

        # fetch the order of our mollie ID
        # from our sales channel mollie profile
        $mollieOrder = $this->gatewayMollie->getOrder($mollieOrderId);

        # --------------------------------------------------------------------------------------------

        $status = $this->statusConverter->getMollieOrderStatus($mollieOrder);

        $this->logger->info('Webhook for order ' . $swOrder->getOrderNumber() . ' and Mollie ID: ' . $mollieOrderId . ' has been received with Status: ' . $status);

        $this->statusUpdater->updatePaymentStatus($transaction, $status, $contextSC->getContext());

        $this->statusUpdater->updateOrderStatus($swOrder, $status, $settings, $contextSC->getContext());

        # --------------------------------------------------------------------------------------------

        # lets check what payment method has been used
        # if somehow the user switched to a different one
        # then we want to update the one inside Shopware
        # If our order is Apple Pay, then DO NOT CONVERT THIS TO CREDIT CARD!
        # we want to keep apple pay as payment method
        if ($transaction->getPaymentMethod() instanceof PaymentMethodEntity && $transaction->getPaymentMethod()->getHandlerIdentifier() !== ApplePayPayment::class) {
            $this->updatePaymentMethod($transaction, $mollieOrder, $contextSC->getContext());
        }

        # --------------------------------------------------------------------------------------------
        # FIRE FLOW BUILDER TRIGGER EVENT
        # we have an adapter setup anyway here, so if flow builder is
        # not yet supported in this shopware version, then
        # this only triggers a dummy dispatcher ;)
        $this->fireFlowBuilderEvents($swOrder, $status, $contextSC->getContext());

    }


    /**
     * @param string $transactionId
     * @param Context $context
     * @return OrderTransactionEntity|null
     */
    private function getTransaction(string $transactionId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $transactionId));
        $criteria->addAssociation('order');
        $criteria->addAssociation('paymentMethod');

        return $this->repoOrderTransactions->search($criteria, $context)->first();
    }

    /**
     * @param string $orderID
     * @param Context $context
     * @return EntitySearchResult<OrderTransactionEntity>
     */
    private function getOrderTransactions(string $orderID, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order.id', $orderID));
        $criteria->addAssociation('order');
        $criteria->addAssociation('paymentMethod');
        $criteria->addSorting(new FieldSorting('createdAt'));

        return $this->repoOrderTransactions->search($criteria, $context);
    }

    /**
     * @param OrderEntity $order
     * @return string
     */
    private function getMollieId(OrderEntity $order): string
    {
        $customFields = $order->getCustomFields();

        if (!isset($customFields['mollie_payments'])) {
            return "";
        }

        if (!isset($customFields['mollie_payments']['order_id'])) {
            return "";
        }

        return (string)$customFields['mollie_payments']['order_id'];
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
            new MultiFilter('AND', [
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

        $this->repoOrderTransactions->update([
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

            case MolliePaymentStatus::MOLLIE_PAYMENT_PENDING;
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
