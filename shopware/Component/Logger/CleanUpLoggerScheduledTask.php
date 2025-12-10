<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('shopware.scheduled_task')]
final class CleanUpLoggerScheduledTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'mollie.order.logger_cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }
}
