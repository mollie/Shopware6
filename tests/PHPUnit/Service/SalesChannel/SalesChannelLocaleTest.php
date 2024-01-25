<?php

namespace Kiener\MolliePayments\Tests\Service\SalesChannel;

use Kiener\MolliePayments\Service\SalesChannel\SalesChannelLocale;
use MolliePayments\Tests\Fakes\Repositories\FakeLanguageRepository;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelLocaleTest extends TestCase
{

    /**
     * @var SalesChannelContext
     */
    private $fakeSalesChannelContext;


    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeSalesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
    }


    /**
     * This test verifies that the available locales are correct.
     * This is a list of possible values that Mollie allows.
     *
     * @return void
     */
    public function testAvailableLocales(): void
    {
        # our data provider has no flat list of values, but we still want to reuse it.
        # so lets just extract the first internal value of each item to get a flat list
        # of expected locales.
        $expected = array_map(function ($list) {
            return $list[0];
        }, $this->getAvailableLocales());

        $this->assertEquals($expected, SalesChannelLocale::AVAILABLE_LOCALES);
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
            ['lt_LT']
        ];
    }

    /**
     * This test verifies that a locale is correctly returned from a sales channel if available.
     * We fake the repository that returns us a given locale for our sales channel.
     * That locale is in the list of available locales and should therefore be correctly returned in our function.
     *
     * @dataProvider getAvailableLocales
     *
     * @param string $locale
     * @return void
     */
    public function testAvailableLocalesAreFound(string $locale): void
    {
        $scLanguage = $this->buildSalesChannelLanguage($locale);

        $repoLanguages = new FakeLanguageRepository($scLanguage);

        $service = new SalesChannelLocale($repoLanguages);

        $detectedLocale = $service->getLocale($this->fakeSalesChannelContext);

        $this->assertEquals($locale, $detectedLocale);
    }

    /**
     * This test verifies that an invalid locale leads to our default locale which
     * is en_GB as result.
     */
    public function testInvalidLocaleLeadsToEnglishDefault(): void
    {
        $scLanguage = $this->buildSalesChannelLanguage('zz_ZZ');

        $repoLanguages = new FakeLanguageRepository($scLanguage);

        $service = new SalesChannelLocale($repoLanguages);

        $detectedLocale = $service->getLocale($this->fakeSalesChannelContext);

        $this->assertEquals('en_GB', $detectedLocale);
    }

    /**
     * This test verifies that we get en_GB as default if our sales channel
     * does not have a locale or language set.
     *
     * @return void
     */
    public function testSalesChannelWithoutLocale(): void
    {
        $repoLanguages = new FakeLanguageRepository(null);

        $service = new SalesChannelLocale($repoLanguages);

        $detectedLocale = $service->getLocale($this->fakeSalesChannelContext);

        $this->assertEquals('en_GB', $detectedLocale);
    }

    /**
     * @param string $locale
     * @return LanguageEntity
     */
    private function buildSalesChannelLanguage(string $locale): LanguageEntity
    {
        # we always need to make sure to use this pattern nl-NL
        # this is how it looks like in Shopware
        $locale = str_replace('_', '-', $locale);

        $foundLocale = new LocaleEntity();
        $foundLocale->setCode($locale);

        $scLanguage = new LanguageEntity();
        $scLanguage->setLocale($foundLocale);

        return $scLanguage;
    }

}
