<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StatusUpdate;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

final class UpdateStatusScheduledTask extends ScheduledTask
{
    protected const MINUTELY = 60;

    public static function getTaskName(): string
    {
        return 'mollie.status.update';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY;
    }
}
