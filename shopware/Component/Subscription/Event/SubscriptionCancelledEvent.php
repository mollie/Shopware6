<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

final class SubscriptionCancelledEvent extends SubscriptionActionEvent
{
    protected function getEventName(): string
    {
        return 'cancelled';
    }
}
