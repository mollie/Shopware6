<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\ScheduledTask\RenewalReminder;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class RenewalReminderTask extends ScheduledTask
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
        return 60; // 12h
        #  return 43200; // 12h
    }

}
