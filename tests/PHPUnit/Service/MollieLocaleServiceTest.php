<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Service\MollieLocaleService;
use MolliePayments\Tests\Fakes\Repositories\FakeLanguageRepository;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MollieLocaleServiceTest extends TestCase
{
    /**
     * @var SalesChannelContext
     */
    private $fakeSalesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSalesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
    }

    /**
     * This test verifies that the available locales are correct.
     * This is a list of possible values that Mollie allows.
     */
    public function testAvailableLocales(): void
    {
        // our data provider has no flat list of values, but we still want to reuse it.
        // so lets just extract the first internal value of each item to get a flat list
        // of expected locales.
        $expected = array_map(static function ($list) {
            return $list[0];
        }, $this->getAvailableLocales());

        $this->assertEquals($expected, MollieLocaleService::AVAILABLE_LOCALES);
    }

    /**
     * This test verifies that a locale is correctly returned from a sales channel if available.
     * We fake the repository that returns us a given locale for our sales channel.
     * That locale is in the list of available locales and should therefore be correctly returned in our function.
     *
     * @dataProvider getAvailableLocales
     */
    public function testAvailableLocalesAreFound(string $locale): void
    {
        $scLanguage = $this->buildSalesChannelLanguage($locale);

        $repoLanguages = new FakeLanguageRepository($scLanguage);

        $service = new MollieLocaleService($repoLanguages);

        $detectedLocale = $service->getLocale($this->fakeSalesChannelContext);

        $this->assertEquals($locale, $detectedLocale);
    }

    /**
     * @return string[]
     */
    public function getAvailableLocales(): array
    {
        return [
            ['en_US'],
            ['en_GB'],
            ['nl_NL'],
            ['fr_FR'],
            ['it_IT'],
            ['de_DE'],
            ['de_AT'],
            ['de_CH'],
            ['es_ES'],
            ['ca_ES'],
            ['nb_NO'],
            ['pt_PT'],
            ['sv_SE'],
            ['fi_FI'],
            ['da_DK'],
            ['is_IS'],
            ['hu_HU'],
            ['pl_PL'],
            ['lv_LV'],
            ['lt_LT'],
        ];
    }

    /**
     * This test verifies that an invalid locale leads to our default locale which
     * is en_GB as result.
     */
    public function testInvalidLocaleLeadsToEnglishDefault(): void
    {
        $scLanguage = $this->buildSalesChannelLanguage('zz_ZZ');

        $repoLanguages = new FakeLanguageRepository($scLanguage);

        $service = new MollieLocaleService($repoLanguages);

        $detectedLocale = $service->getLocale($this->fakeSalesChannelContext);

        $this->assertEquals('en_GB', $detectedLocale);
    }

    /**
     * This test verifies that we get en_GB as default if our sales channel
     * does not have a locale or language set.
     */
    public function testSalesChannelWithoutLocale(): void
    {
        $repoLanguages = new FakeLanguageRepository(null);

        $service = new MollieLocaleService($repoLanguages);

        $detectedLocale = $service->getLocale($this->fakeSalesChannelContext);

        $this->assertEquals('en_GB', $detectedLocale);
    }

    /**
     * @dataProvider mollieLocaleDataProvider
     */
    public function testProvidesMollieLocale(string $input, string $expected): void
    {
        $service = new MollieLocaleService(new FakeLanguageRepository(null));

        $this->assertEquals($expected, $service->getMollieLocale($input));
    }

    public function mollieLocaleDataProvider(): array
    {
        return [
            'en_US' => ['en-US', 'en_US'],
            'en_GB' => ['en-GB', 'en_GB'],
            'nl_NL' => ['nl-NL', 'nl_NL'],
            'fr_FR' => ['fr-FR', 'fr_FR'],
            'it_IT' => ['it-IT', 'it_IT'],
            'de_DE' => ['de-DE', 'de_DE'],
            'de_AT' => ['de-AT', 'de_AT'],
            'de_CH' => ['de-CH', 'de_CH'],
            'es_ES' => ['es-ES', 'es_ES'],
            'ca_ES' => ['ca-ES', 'ca_ES'],
            'nb_NO' => ['nb-NO', 'nb_NO'],
            'pt_PT' => ['pt-PT', 'pt_PT'],
            'sv_SE' => ['sv-SE', 'sv_SE'],
            'fi_FI' => ['fi-FI', 'fi_FI'],
            'da_DK' => ['da-DK', 'da_DK'],
            'is_IS' => ['is-IS', 'is_IS'],
            'hu_HU' => ['hu-HU', 'hu_HU'],
            'pl_PL' => ['pl-PL', 'pl_PL'],
            'lv_LV' => ['lv-LV', 'lv_LV'],
            'lt_LT' => ['lt-LT', 'lt_LT'],
        ];
    }

    private function buildSalesChannelLanguage(string $locale): LanguageEntity
    {
        // we always need to make sure to use this pattern nl-NL
        // this is how it looks like in Shopware
        $locale = str_replace('_', '-', $locale);

        $foundLocale = new LocaleEntity();
        $foundLocale->setCode($locale);
        $foundLocale->setUniqueIdentifier('test-locale');

        $scLanguage = new LanguageEntity();
        $scLanguage->setLocale($foundLocale);
        $scLanguage->setUniqueIdentifier('test-language');

        return $scLanguage;
    }
}
