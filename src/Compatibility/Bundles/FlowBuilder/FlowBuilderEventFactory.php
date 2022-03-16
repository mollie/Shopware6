<?php

namespace Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\DummyEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStartedEvent;
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
use Kiener\MolliePayments\Service\Mollie\MolliePaymentStatus;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Update\Struct\Version;

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
     * @return DummyEvent|RefundStartedEvent
     */
    public function buildRefundStartedEvent(OrderEntity $orderEntity, float $amount, Context $context)
    {
        if ($this->versionCompare->lt(FlowBuilderFactory::FLOW_BUILDER_MIN_VERSION)) {
            return new DummyEvent();
        }

        return new RefundStartedEvent($orderEntity, $amount, $context);
    }

}
