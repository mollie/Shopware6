<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Hydrator;


/**
 * @copyright 2021 dasistweb GmbH (https://www.dasistweb.de)
 */
class MolliePriceHydrator
{
    public const MOLLIE_FALLBACK_CURRENCY_CODE = 'EUR';

    public const MOLLIE_PRICE_PRECISION = 2;
    
    public function hydrate(?float $price, ?string $currency): array
    {
        if (empty($currency)) {
            $currency = self::MOLLIE_FALLBACK_CURRENCY_CODE;
        }

        if (is_null($price)) {
            $price = 0.0;
        }

        return [
            'currency' => $currency,
            'value' => number_format(round($price, self::MOLLIE_PRICE_PRECISION), self::MOLLIE_PRICE_PRECISION, '.', '')
        ];
    }
}
