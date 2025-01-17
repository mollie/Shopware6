<?php declare(strict_types=1);

namespace Kiener\MolliePayments\ScheduledTask\Subscription\RenewalReminder;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class RenewalReminderTaskDev extends ScheduledTask
{
    /**
     * @return string
     */
    public static function getTaskName(): string
    {
        return 'mollie.subscriptions.renewal_reminder';
    }

    /**
     * @return int
     */
    public static function getDefaultInterval(): int
    {
        return 60; // 1 minute
    }
}
