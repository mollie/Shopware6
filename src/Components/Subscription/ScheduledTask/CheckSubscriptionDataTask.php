<?php declare(strict_types=1);


namespace Kiener\MolliePayments\Components\Subscription\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CheckSubscriptionDataTask extends ScheduledTask
{
    /**
     * @return string
     */
    public static function getTaskName(): string
    {
        return 'mollie.check_subscription_data_task';
    }

    /**
     * @return int
     */
    public static function getDefaultInterval(): int
    {
        return 21600; // 6h
    }
}
