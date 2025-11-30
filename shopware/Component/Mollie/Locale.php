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
    case frFR = 'fr_FR';
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

        $languageLocale = str_replace('-', '_', $code);

        return self::from($languageLocale);
    }
}
