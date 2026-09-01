<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusAuthorizedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusCancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusChargebackEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusExpiredEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusFailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusOpenEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPartiallyRefundedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPendingEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusRefundedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Event\FlowEventAware;

enum PaymentStatus: string
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';

    /**
     * The following statuses do not exist in the Mollie Payments API. A charged back or refunded
     * payment keeps its "paid" status there and only exposes positive "amountChargedBack" /
     * "amountRefunded" values. Payment::createFromClientResponse derives these implicit statuses
     * from those amounts so the rest of the application can rely on a single status value.
     */
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case CHARGEBACK = 'chargeback';

    public function isFailed(): bool
    {
        $failedStatus = [
            self::FAILED,
            self::CANCELED,
            self::EXPIRED,
        ];

        return in_array($this, $failedStatus, true);
    }

    public function isCanceled(): bool
    {
        return $this === self::CANCELED;
    }

    public function isApproved(): bool
    {
        return in_array($this, [
            self::OPEN,
            self::PENDING,
            self::AUTHORIZED,
            self::PAID,
        ], true);
    }

    /**
     * Refunds and chargebacks legitimately change the state of an already paid order, so the webhook
     * must keep applying them even when the order is already paid (see WebhookRoute).
     */
    public function isRefundRelated(): bool
    {
        return in_array($this, [
            self::REFUNDED,
            self::PARTIALLY_REFUNDED,
            self::CHARGEBACK,
        ], true);
    }

    /**
     * @return class-string<FlowEventAware>[]
     */
    public static function getAllWebhookEvents(): array
    {
        return [
            WebhookStatusOpenEvent::class,
            WebhookStatusPendingEvent::class,
            WebhookStatusAuthorizedEvent::class,
            WebhookStatusPaidEvent::class,
            WebhookStatusCancelledEvent::class,
            WebhookStatusExpiredEvent::class,
            WebhookStatusFailedEvent::class,
            WebhookStatusRefundedEvent::class,
            WebhookStatusPartiallyRefundedEvent::class,
            WebhookStatusChargebackEvent::class,
        ];
    }

    /**
     * @return class-string
     */
    public function getWebhookEventClass(): string
    {
        return match ($this) {
            self::OPEN => WebhookStatusOpenEvent::class,
            self::PENDING => WebhookStatusPendingEvent::class,
            self::AUTHORIZED => WebhookStatusAuthorizedEvent::class,
            self::PAID => WebhookStatusPaidEvent::class,
            self::CANCELED => WebhookStatusCancelledEvent::class,
            self::EXPIRED => WebhookStatusExpiredEvent::class,
            self::FAILED => WebhookStatusFailedEvent::class,
            self::REFUNDED => WebhookStatusRefundedEvent::class,
            self::PARTIALLY_REFUNDED => WebhookStatusPartiallyRefundedEvent::class,
            self::CHARGEBACK => WebhookStatusChargebackEvent::class,
        };
    }

    public function getShopwarePaymentStatus(): string
    {
        return match ($this) {
            self::OPEN => OrderTransactionStates::STATE_OPEN,
            self::PENDING => OrderTransactionStates::STATE_UNCONFIRMED,
            self::AUTHORIZED => OrderTransactionStates::STATE_AUTHORIZED,
            self::PAID => OrderTransactionStates::STATE_PAID,
            self::CANCELED, self::EXPIRED => OrderTransactionStates::STATE_CANCELLED,
            self::FAILED => OrderTransactionStates::STATE_FAILED,
            self::REFUNDED => OrderTransactionStates::STATE_REFUNDED,
            self::PARTIALLY_REFUNDED => OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
            self::CHARGEBACK => OrderTransactionStates::STATE_CHARGEBACK,
        };
    }

    public function getShopwareHandlerMethod(): string
    {
        return match ($this) {
            self::PAID => 'paid',
            self::CANCELED, self::EXPIRED => 'cancel',
            self::AUTHORIZED => 'authorize',
            self::FAILED => 'fail',
            self::REFUNDED => 'refund',
            self::PARTIALLY_REFUNDED => 'refundPartially',
            self::CHARGEBACK => 'chargeback',
            default => '',
        };
    }
}
