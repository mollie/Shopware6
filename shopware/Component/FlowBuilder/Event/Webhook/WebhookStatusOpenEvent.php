<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event\Webhook;

use Mollie\Shopware\Component\Mollie\PaymentStatus;

final class WebhookStatusOpenEvent extends WebhookEvent
{
    protected static function getStatus(): string
    {
        return PaymentStatus::OPEN;
    }
}
