<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Subscription\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class SendPrePaymentReminderEmailTask extends ScheduledTask
{
    /**
     * @return string
     */
    public static function getTaskName(): string
    {
        return 'mollie.send_pre_payment_reminder_email_task';
    }

    /**
     * @return int
     */
    public static function getDefaultInterval(): int
    {
        return 43200; // 12h
    }
}
