<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderCanceled\OrderCanceledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderCanceled\OrderCanceledEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderFailed\OrderFailedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderFailed\OrderFailedEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderSuccess\OrderSuccessEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderSuccess\OrderSuccessEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\DummyEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStarted\RefundStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStarted\RefundStartedEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionCancelled\SubscriptionCancelledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionCancelled\SubscriptionCancelledEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionEnded\SubscriptionEndedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionEnded\SubscriptionEndedEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionPaused\SubscriptionPausedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionPaused\SubscriptionPausedEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionReminded\SubscriptionRemindedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionReminded\SubscriptionRemindedEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRenewed\SubscriptionRenewedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRenewed\SubscriptionRenewedEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionResumed\SubscriptionResumedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionResumed\SubscriptionResumedEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionSkipped\SubscriptionSkippedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionSkipped\SubscriptionSkippedEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionStarted\SubscriptionStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionStarted\SubscriptionStartedEvent65;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookReceivedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedAuthorizedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedCancelledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedChargebackEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedCompletedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedExpiredEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedFailedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedPaidEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedPartialRefundedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedPendingEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\WebhookStatusReceived\WebhookReceivedRefundedEvent;
use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class FlowBuilderEventFactory
{

    /**
     * @var VersionCompare
     */
    private $versionCompare;


    /**
     * @param string $shopwareVersion
     */
    public function __construct(string $shopwareVersion)
    {
        $this->versionCompare = new VersionCompare($shopwareVersion);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param string $status
     * @param Context $context
     * @return DummyEvent|WebhookReceivedEvent
     */
    public function buildWebhookReceivedAll(OrderEntity $orderEntity, string $status, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedEvent($orderEntity, $status, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     * @return DummyEvent|WebhookReceivedFailedEvent
     */
    public function buildWebhookReceivedFailedEvent(OrderEntity $orderEntity, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedFailedEvent($orderEntity, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     * @return DummyEvent|WebhookReceivedCancelledEvent
     */
    public function buildWebhookReceivedCancelledEvent(OrderEntity $orderEntity, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedCancelledEvent($orderEntity, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     * @return DummyEvent|WebhookReceivedExpiredEvent
     */
    public function buildWebhookReceivedExpiredEvent(OrderEntity $orderEntity, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedExpiredEvent($orderEntity, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     * @return DummyEvent|WebhookReceivedPendingEvent
     */
    public function buildWebhookReceivedPendingEvent(OrderEntity $orderEntity, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedPendingEvent($orderEntity, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     * @return DummyEvent|WebhookReceivedAuthorizedEvent
     */
    public function buildWebhookReceivedAuthorizedEvent(OrderEntity $orderEntity, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedAuthorizedEvent($orderEntity, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     * @return DummyEvent|WebhookReceivedPaidEvent
     */
    public function buildWebhookReceivedPaidEvent(OrderEntity $orderEntity, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedPaidEvent($orderEntity, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     * @return DummyEvent|WebhookReceivedChargebackEvent
     */
    public function buildWebhookReceivedChargebackEvent(OrderEntity $orderEntity, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedChargebackEvent($orderEntity, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     * @return DummyEvent|WebhookReceivedRefundedEvent
     */
    public function buildWebhookReceivedRefundedEvent(OrderEntity $orderEntity, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedRefundedEvent($orderEntity, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     * @return DummyEvent|WebhookReceivedPartialRefundedEvent
     */
    public function buildWebhookReceivedPartialRefundedEvent(OrderEntity $orderEntity, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedPartialRefundedEvent($orderEntity, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param Context $context
     * @return DummyEvent|WebhookReceivedCompletedEvent
     */
    public function buildWebhookReceivedCompletedEvent(OrderEntity $orderEntity, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new WebhookReceivedCompletedEvent($orderEntity, $context);
    }

    /**
     * @param OrderEntity $orderEntity
     * @param float $amount
     * @param Context $context
     * @return DummyEvent|RefundStartedEvent|RefundStartedEvent65
     */
    public function buildRefundStartedEvent(OrderEntity $orderEntity, float $amount, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new RefundStartedEvent65($orderEntity, $amount, $context);
        }

        return new RefundStartedEvent($orderEntity, $amount, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param SalesChannelEntity $salesChannel
     * @param Context $context
     * @return DummyEvent|SubscriptionRemindedEvent|SubscriptionRemindedEvent65
     */
    public function buildSubscriptionRemindedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, SalesChannelEntity $salesChannel, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new SubscriptionRemindedEvent65($customer, $subscription, $salesChannel, $context);
        }

        return new SubscriptionRemindedEvent($customer, $subscription, $salesChannel, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionStartedEvent|SubscriptionStartedEvent65
     */
    public function buildSubscriptionStartedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new SubscriptionStartedEvent65($subscription, $customer, $context);
        }

        return new SubscriptionStartedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionEndedEvent|SubscriptionEndedEvent65
     */
    public function buildSubscriptionEndedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new SubscriptionEndedEvent65($subscription, $customer, $context);
        }

        return new SubscriptionEndedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionCancelledEvent|SubscriptionCancelledEvent65
     */
    public function buildSubscriptionCancelledEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new SubscriptionCancelledEvent65($subscription, $customer, $context);
        }

        return new SubscriptionCancelledEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionPausedEvent|SubscriptionPausedEvent65
     */
    public function buildSubscriptionPausedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new SubscriptionPausedEvent65($subscription, $customer, $context);
        }

        return new SubscriptionPausedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionResumedEvent|SubscriptionResumedEvent65
     */
    public function buildSubscriptionResumedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new SubscriptionResumedEvent65($subscription, $customer, $context);
        }

        return new SubscriptionResumedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionSkippedEvent|SubscriptionSkippedEvent65
     */
    public function buildSubscriptionSkippedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new SubscriptionSkippedEvent65($subscription, $customer, $context);
        }

        return new SubscriptionSkippedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionRenewedEvent|SubscriptionRenewedEvent65
     */
    public function buildSubscriptionRenewedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new SubscriptionRenewedEvent65($subscription, $customer, $context);
        }

        return new SubscriptionRenewedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param OrderEntity $order
     * @param Context $context
     * @return DummyEvent|OrderSuccessEvent|OrderSuccessEvent65
     */
    public function buildOrderSuccessEvent(CustomerEntity $customer, OrderEntity $order, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new OrderSuccessEvent65($order, $customer, $context);
        }

        return new OrderSuccessEvent($order, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param OrderEntity $order
     * @param Context $context
     * @return DummyEvent|OrderFailedEvent|OrderFailedEvent65
     */
    public function buildOrderFailedEvent(CustomerEntity $customer, OrderEntity $order, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new OrderFailedEvent65($order, $customer, $context);
        }

        return new OrderFailedEvent($order, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param OrderEntity $order
     * @param Context $context
     * @return DummyEvent|OrderCanceledEvent|OrderCanceledEvent65
     */
    public function buildOrderCanceledEvent(CustomerEntity $customer, OrderEntity $order, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte('6.5.0.0')) {
            return new OrderCanceledEvent65($order, $customer, $context);
        }

        return new OrderCanceledEvent($order, $customer, $context);
    }
}
