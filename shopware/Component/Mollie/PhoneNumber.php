<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class PhoneNumber
{
    /**
     * E.164 phone numbers start with a "+", a non-zero country code and hold at most 15 digits.
     */
    public const E164_PATTERN = '/^\+[1-9]\d{1,14}$/';

    /**
     * Country calling codes for countries whose national number format uses a "0" trunk
     * prefix that is dropped in the international format. Only for these countries a number
     * in national format (leading "0") can be converted safely. Countries without a trunk
     * prefix (e.g. ES, DK, NO) or with a non-standard one (e.g. IT keeps the leading zero,
     * HU uses "06") are intentionally not listed.
     */
    private const COUNTRY_CALLING_CODES = [
        'AT' => '+43',
        'BE' => '+32',
        'CH' => '+41',
        'DE' => '+49',
        'FI' => '+358',
        'FR' => '+33',
        'GB' => '+44',
        'IE' => '+353',
        'LU' => '+352',
        'NL' => '+31',
        'SE' => '+46',
    ];

    public static function isValidE164(string $phone): bool
    {
        return preg_match(self::E164_PATTERN, $phone) === 1;
    }

    /**
     * Tries to convert a customer-entered phone number to E.164: separators and a "(0)" infix
     * are stripped, an international "00" prefix becomes "+" and a number in national format
     * (leading "0") gets the calling code of the given address country. Returns an empty
     * string when the number cannot be converted to a valid E.164 representation.
     */
    public static function toE164(string $phone, string $countryIso): string
    {
        $phone = str_replace('(0)', '', trim($phone));
        $phone = (string) preg_replace('/[\s\/\-\(\)\.]+/', '', $phone);

        if ($phone === '') {
            return '';
        }

        if (strncmp($phone, '00', 2) === 0) {
            $phone = '+' . substr($phone, 2);
        } elseif (strncmp($phone, '0', 1) === 0) {
            $callingCode = self::COUNTRY_CALLING_CODES[strtoupper($countryIso)] ?? null;
            if ($callingCode === null) {
                return '';
            }
            $phone = $callingCode . substr($phone, 1);
        }

        return self::isValidE164($phone) ? $phone : '';
    }
}
