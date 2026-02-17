<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

final class SubscriptionPausedEvent extends SubscriptionActionEvent
{
    protected function getEventName(): string
    {
        return 'paused';
    }
}
