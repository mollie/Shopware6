<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

enum VoucherCategory: string
{
    case ECO = 'eco';
    case GIFT = 'gift';
    case MEAL = 'meal';
    case SPORT_CULTURE = 'sport_culture';

    case CONSUME = 'consume';
    case ADDITIONAL = 'additional';

    public static function tryFromNumber(int $number): ?self
    {
        return match ($number) {
            1 => VoucherCategory::ECO,
            2 => VoucherCategory::GIFT,
            3 => VoucherCategory::MEAL,
            default => null,
        };
    }
}
