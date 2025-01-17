<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderCanceled\OrderCanceledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderCanceled\OrderCanceledEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderFailed\OrderFailedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderFailed\OrderFailedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderSuccess\OrderSuccessEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Checkout\OrderSuccess\OrderSuccessEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\DummyEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStarted\RefundStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStarted\RefundStartedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionCancelled\SubscriptionCancelledEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionCancelled\SubscriptionCancelledEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionEnded\SubscriptionEndedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionEnded\SubscriptionEndedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionPaused\SubscriptionPausedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionPaused\SubscriptionPausedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionReminded\SubscriptionRemindedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionReminded\SubscriptionRemindedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRenewed\SubscriptionRenewedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionRenewed\SubscriptionRenewedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionResumed\SubscriptionResumedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionResumed\SubscriptionResumedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionSkipped\SubscriptionSkippedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionSkipped\SubscriptionSkippedEvent651;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionStarted\SubscriptionStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Subscription\SubscriptionStarted\SubscriptionStartedEvent651;
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


    private const SW_VERSION_651 = '6.5.1.0';


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
     * @return DummyEvent|RefundStartedEvent|RefundStartedEvent651
     */
    public function buildRefundStartedEvent(OrderEntity $orderEntity, float $amount, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new RefundStartedEvent651($orderEntity, $amount, $context);
        }

        return new RefundStartedEvent($orderEntity, $amount, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param SalesChannelEntity $salesChannel
     * @param Context $context
     * @return DummyEvent|SubscriptionRemindedEvent|SubscriptionRemindedEvent651
     */
    public function buildSubscriptionRemindedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, SalesChannelEntity $salesChannel, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new SubscriptionRemindedEvent651($customer, $subscription, $salesChannel, $context);
        }

        return new SubscriptionRemindedEvent($customer, $subscription, $salesChannel, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionStartedEvent|SubscriptionStartedEvent651
     */
    public function buildSubscriptionStartedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new SubscriptionStartedEvent651($subscription, $customer, $context);
        }

        return new SubscriptionStartedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionEndedEvent|SubscriptionEndedEvent651
     */
    public function buildSubscriptionEndedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new SubscriptionEndedEvent651($subscription, $customer, $context);
        }

        return new SubscriptionEndedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionCancelledEvent|SubscriptionCancelledEvent651
     */
    public function buildSubscriptionCancelledEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new SubscriptionCancelledEvent651($subscription, $customer, $context);
        }

        return new SubscriptionCancelledEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionPausedEvent|SubscriptionPausedEvent651
     */
    public function buildSubscriptionPausedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new SubscriptionPausedEvent651($subscription, $customer, $context);
        }

        return new SubscriptionPausedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionResumedEvent|SubscriptionResumedEvent651
     */
    public function buildSubscriptionResumedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new SubscriptionResumedEvent651($subscription, $customer, $context);
        }

        return new SubscriptionResumedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionSkippedEvent|SubscriptionSkippedEvent651
     */
    public function buildSubscriptionSkippedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new SubscriptionSkippedEvent651($subscription, $customer, $context);
        }

        return new SubscriptionSkippedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @return DummyEvent|SubscriptionRenewedEvent|SubscriptionRenewedEvent651
     */
    public function buildSubscriptionRenewedEvent(CustomerEntity $customer, SubscriptionEntity $subscription, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new SubscriptionRenewedEvent651($subscription, $customer, $context);
        }

        return new SubscriptionRenewedEvent($subscription, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param OrderEntity $order
     * @param Context $context
     * @return DummyEvent|OrderSuccessEvent|OrderSuccessEvent651
     */
    public function buildOrderSuccessEvent(CustomerEntity $customer, OrderEntity $order, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new OrderSuccessEvent651($order, $customer, $context);
        }

        return new OrderSuccessEvent($order, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param OrderEntity $order
     * @param Context $context
     * @return DummyEvent|OrderFailedEvent|OrderFailedEvent651
     */
    public function buildOrderFailedEvent(CustomerEntity $customer, OrderEntity $order, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new OrderFailedEvent651($order, $customer, $context);
        }

        return new OrderFailedEvent($order, $customer, $context);
    }

    /**
     * @param CustomerEntity $customer
     * @param OrderEntity $order
     * @param Context $context
     * @return DummyEvent|OrderCanceledEvent|OrderCanceledEvent651
     */
    public function buildOrderCanceledEvent(CustomerEntity $customer, OrderEntity $order, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        if ($this->versionCompare->gte(self::SW_VERSION_651)) {
            return new OrderCanceledEvent651($order, $customer, $context);
        }

        return new OrderCanceledEvent($order, $customer, $context);
    }
}
