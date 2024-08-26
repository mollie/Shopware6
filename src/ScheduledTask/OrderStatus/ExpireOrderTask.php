<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\ScheduledTask\OrderStatus;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class ExpireOrderTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'mollie.order_status.expire';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }
}
