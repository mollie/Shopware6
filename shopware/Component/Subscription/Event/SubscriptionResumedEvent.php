<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

final class SubscriptionResumedEvent extends SubscriptionActionEvent
{
    protected function getEventName(): string
    {
        return 'resumed';
    }
}
