<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.scheduled_task')]
final class SubscriptionRenewalReminderTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'mollie.subscriptions.renewal_reminder';
    }

    public static function getDefaultInterval(): int
    {
        return 3600;
    }
}
