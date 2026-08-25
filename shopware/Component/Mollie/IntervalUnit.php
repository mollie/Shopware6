<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum IntervalUnit: string
{
    case DAYS = 'days';
    case WEEKS = 'weeks';
    case MONTHS = 'months';

    /**
     * Mollie writes the unit in the singular for a single period ("1 month", not "1 months").
     */
    public function singular(): string
    {
        return match ($this) {
            self::DAYS => 'day',
            self::WEEKS => 'week',
            self::MONTHS => 'month',
        };
    }
}
