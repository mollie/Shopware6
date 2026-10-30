<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

final class PendingSessionPaymentScheduledTask extends ScheduledTask
{
    protected const MINUTELY = 60;

    public static function getTaskName(): string
    {
        return 'mollie.express-components.pending-session-payment';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
