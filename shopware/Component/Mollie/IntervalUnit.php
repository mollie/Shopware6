<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum IntervalUnit: string
{
    case DAYS = 'days';
    case WEEKS = 'weeks';
    case MONTHS = 'months';
}
