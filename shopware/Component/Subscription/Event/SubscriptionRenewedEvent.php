<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

final class SubscriptionRenewedEvent extends SubscriptionActionEvent
{
    protected function getEventName(): string
    {
        return 'renewed';
    }
}
