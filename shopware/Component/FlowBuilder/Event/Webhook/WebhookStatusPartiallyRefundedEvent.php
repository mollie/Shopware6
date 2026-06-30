<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event\Webhook;

use Mollie\Shopware\Component\Mollie\PaymentStatus;

final class WebhookStatusPartiallyRefundedEvent extends WebhookEvent
{
    protected static function getStatus(): string
    {
        return 'status.' . PaymentStatus::PARTIALLY_REFUNDED->value;
    }
}
