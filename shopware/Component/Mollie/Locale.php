<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

enum Locale: string
{
    case enGB = 'en_GB';
    case enUS = 'en_US';
    case nlNL = 'nl_NL';
    case nlBE = 'nl_BE';
    case frFR = 'fr_FR';
    case frBE = 'fr_BE';
    case itIT = 'it_IT';
    case deDE = 'de_DE';
    case deAT = 'de_AT';
    case deCH = 'de_CH';
    case esES = 'es_ES';
    case caES = 'ca_ES';
    case nbNO = 'nb_NO';
    case ptPT = 'pt_PT';
    case svSE = 'sv_SE';
    case fiFI = 'fi_FI';
    case daDK = 'da_DK';
    case isIS = 'is_IS';
    case huHU = 'hu_HU';
    case plPL = 'pl_PL';
    case lvLV = 'lv_LV';
    case ltLT = 'lt_LT';

    public static function fromLanguage(LanguageEntity $language): self
    {
        $locale = $language->getLocale();
        $code = 'en-GB';
        if ($locale instanceof LocaleEntity) {
            $code = $locale->getCode();
        }

        return self::fromLocaleCode($code);
    }

    public static function fromLocaleCode(string $code): self
    {
        $languageLocale = str_replace('-', '_', $code);

        $locale = self::tryFrom($languageLocale);
        if ($locale !== null) {
            return $locale;
        }

        // locale is not supported by Mollie, fall back to one with the same language (e.g. de_LU -> de_DE)
        $parts = explode('_', $languageLocale);
        $language = strtolower($parts[0]);
        if ($language !== '') {
            foreach (self::cases() as $case) {
                if (str_starts_with($case->value, $language . '_')) {
                    return $case;
                }
            }
        }

        // unknown language, fall back to one used in the same region (e.g. gsw_CH -> de_CH)
        $region = strtoupper($parts[1] ?? '');
        if ($region !== '') {
            foreach (self::cases() as $case) {
                if (str_ends_with($case->value, '_' . $region)) {
                    return $case;
                }
            }
        }

        return self::enGB;
    }
}
