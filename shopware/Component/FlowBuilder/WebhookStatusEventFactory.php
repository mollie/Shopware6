<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusAuthorizedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusCancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusExpiredEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusFailedEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusOpenEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPendingEvent;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class WebhookStatusEventFactory
{
    public function getEventList(): array
    {
        return array_values($this->getMapping());
    }

    public function create(Payment $payment, OrderEntity $order, Context $context): WebhookEvent
    {
        $mapping = $this->getMapping();
        $class = $mapping[(string) $payment->getStatus()] ?? null;
        if (null === $class) {
            throw new InvalidWebhookStatusMapping((string) $payment->getStatus());
        }

        return new $class($payment, $order, $context);
    }

    private function getMapping(): array
    {
        return [
            PaymentStatus::OPEN => WebhookStatusOpenEvent::class,
            PaymentStatus::PENDING => WebhookStatusPendingEvent::class,
            PaymentStatus::AUTHORIZED => WebhookStatusAuthorizedEvent::class,
            PaymentStatus::PAID => WebhookStatusPaidEvent::class,
            PaymentStatus::CANCELED => WebhookStatusCancelledEvent::class,
            PaymentStatus::EXPIRED => WebhookStatusExpiredEvent::class,
            PaymentStatus::FAILED => WebhookStatusFailedEvent::class,
        ];
    }
}
