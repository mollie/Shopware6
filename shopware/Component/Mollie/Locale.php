<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

final class Locale extends AbstractEnum
{
    private const AVAILABLE_LOCALES = [
        'en_US',
        'en_GB',
        'nl_NL',
        'fr_FR',
        'it_IT',
        'de_DE',
        'de_AT',
        'de_CH',
        'es_ES',
        'ca_ES',
        'nb_NO',
        'pt_PT',
        'sv_SE',
        'fi_FI',
        'da_DK',
        'is_IS',
        'hu_HU',
        'pl_PL',
        'lv_LV',
        'lt_LT',
    ];

    public static function fromLanguage(LanguageEntity $language): self
    {
        $locale = $language->getLocale();
        $code = 'en-GB';
        if ($locale instanceof LocaleEntity) {
            $code = $locale->getCode();
        }

        $languageLocale = str_replace('-', '_', $code);

        $mollieLocale = self::AVAILABLE_LOCALES[$languageLocale] ?? 'en_GB';

        return new self($mollieLocale);
    }

    /**
     * @return string[]
     */
    protected function getPossibleValues(): array
    {
        return self::AVAILABLE_LOCALES;
    }
}
