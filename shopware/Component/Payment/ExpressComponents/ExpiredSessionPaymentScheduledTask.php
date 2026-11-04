<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

final class ExpiredSessionPaymentScheduledTask extends ScheduledTask
{
    private const QUARTER_HOURLY = 900;

    public static function getTaskName(): string
    {
        return 'mollie.express-components.expired-session-payments';
    }

    public static function getDefaultInterval(): int
    {
        return self::QUARTER_HOURLY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
