<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusAuthorizedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusCancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusExpiredEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusFailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusOpenEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPendingEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;

enum PaymentStatus: string
{
    case OPEN = 'open';
    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';

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

    /**
     * @return class-string[]
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
            self::FAILED => WebhookStatusFailedEvent::class
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
        };
    }

    public function getShopwareHandlerMethod(): string
    {
        return match ($this) {
            self::PAID => 'paid',
            self::CANCELED => 'cancel',
            self::AUTHORIZED => 'authorize',
            self::FAILED => 'fail',
            default => '',
        };
    }
}
