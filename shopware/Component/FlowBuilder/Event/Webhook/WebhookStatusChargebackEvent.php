<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\FlowBuilder\Event\Webhook;

use Mollie\Shopware\Component\Mollie\PaymentStatus;

final class WebhookStatusChargebackEvent extends WebhookEvent
{
    protected static function getStatus(): string
    {
        return 'status.' . PaymentStatus::CHARGEBACK->value;
    }
}
