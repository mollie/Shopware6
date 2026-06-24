<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

final class SubscriptionRemindedEvent extends SubscriptionActionEvent
{
    protected function getEventName(): string
    {
        return 'reminded';
    }
}
