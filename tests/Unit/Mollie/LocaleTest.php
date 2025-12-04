<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

#[CoversClass(Locale::class)]
final class LocaleTest extends TestCase
{
    public function testCanCreateFromLanguage(): void
    {
        $locale = new LocaleEntity();
        $locale->setCode('de-DE');

        $language = new LanguageEntity();
        $language->setLocale($locale);

        $actual = Locale::fromLanguage($language);

        $this->assertSame(Locale::deDE, $actual);
    }

    public function testCanCreateFromLanguageWithoutLocale(): void
    {
        $language = new LanguageEntity();

        $actual = Locale::fromLanguage($language);

        $this->assertSame(Locale::enGB, $actual);
    }

    public function testCanCreateFromLanguageWithDifferentLocales(): void
    {
        $testCases = [
            'en-GB' => Locale::enGB,
            'en-US' => Locale::enUS,
            'nl-NL' => Locale::nlNL,
            'fr-FR' => Locale::frFR,
            'it-IT' => Locale::itIT,
            'de-DE' => Locale::deDE,
            'de-AT' => Locale::deAT,
            'de-CH' => Locale::deCH,
            'es-ES' => Locale::esES,
            'ca-ES' => Locale::caES,
            'nb-NO' => Locale::nbNO,
            'pt-PT' => Locale::ptPT,
            'sv-SE' => Locale::svSE,
            'fi-FI' => Locale::fiFI,
            'da-DK' => Locale::daDK,
            'is-IS' => Locale::isIS,
            'hu-HU' => Locale::huHU,
            'pl-PL' => Locale::plPL,
            'lv-LV' => Locale::lvLV,
            'lt-LT' => Locale::ltLT,
        ];

        foreach ($testCases as $code => $expectedLocale) {
            $locale = new LocaleEntity();
            $locale->setCode($code);

            $language = new LanguageEntity();
            $language->setLocale($locale);

            $actual = Locale::fromLanguage($language);

            $this->assertSame($expectedLocale, $actual);
        }
    }
}