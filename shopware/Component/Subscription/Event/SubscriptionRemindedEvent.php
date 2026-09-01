<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Event;

final class SubscriptionRemindedEvent extends SubscriptionActionEvent
{
    /**
     * Kept as the legacy name "renewal_reminder" (full event:
     * mollie.subscription.renewal_reminder) so flows configured before the
     * refactor keep firing. Renaming this silently orphans existing flows.
     */
    protected function getEventName(): string
    {
        return 'renewal_reminder';
    }
}
