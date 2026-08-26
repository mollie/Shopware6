<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Page;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\Page\SubscriptionPage;
use Mollie\Shopware\Component\Subscription\Page\SubscriptionPageLoader;
use Mollie\Shopware\Unit\Fake\FakeCountryRoute;
use Mollie\Shopware\Unit\Fake\FakeGenericPageLoader;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSalutationRoute;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeListSubscriptionsRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(SubscriptionPageLoader::class)]
final class SubscriptionPageLoaderTest extends TestCase
{
    private FakeCountryRoute $countryRoute;

    protected function setUp(): void
    {
        $this->countryRoute = new FakeCountryRoute(new CountryCollection([$this->country()]));
    }

    public function testTheAccountPageIsNotIndexedBySearchEngines(): void
    {
        // The page lists a customer's own subscriptions, so it must not end up in a search index.
        $loader = $this->buildLoader(metaInformation: new MetaInformation());

        $page = $loader->load(new Request(), new FakeSalesChannelContext());

        self::assertSame('noindex,follow', $page->getMetaInformation()?->getRobots());
    }

    public function testAPageWithoutMetaInformationStillLoads(): void
    {
        $loader = $this->buildLoader();

        $page = $loader->load(new Request(), new FakeSalesChannelContext());

        self::assertInstanceOf(SubscriptionPage::class, $page);
        self::assertNull($page->getMetaInformation());
    }

    public function testTheSubscriptionsOfTheCustomerAreOnThePage(): void
    {
        $subscriptions = new SubscriptionCollection([SubscriptionEntityBuilder::create()->withId('subscription-id')->build()]);
        $loader = $this->buildLoader(subscriptions: $subscriptions);

        $page = $loader->load(new Request(), new FakeSalesChannelContext());

        self::assertSame(1, $page->getSubscriptions()->getTotal());
    }

    public function testCountriesAndSalutationsForTheAddressFormAreOnThePage(): void
    {
        $loader = $this->buildLoader();

        $page = $loader->load(new Request(), new FakeSalesChannelContext());

        self::assertCount(1, $page->getCountries());
        self::assertCount(1, $page->getSalutations());
    }

    public function testCountriesAreLoadedWithTheirStatesAndInDisplayOrder(): void
    {
        $loader = $this->buildLoader();

        $loader->load(new Request(), new FakeSalesChannelContext());

        $criteria = $this->countryRoute->getCriteria();
        self::assertCount(1, $criteria);
        self::assertArrayHasKey('states', $criteria[0]->getAssociations());
        self::assertSame(
            ['position', 'name', 'states.position', 'states.name'],
            array_map(static fn (FieldSorting $sorting): string => $sorting->getField(), $criteria[0]->getSorting())
        );
    }

    public function testTheStorefrontSeesWhichActionsTheMerchantAllowed(): void
    {
        $settings = new SubscriptionSettings(
            enabled: true,
            allowEditAddress: true,
            allowPauseAndResume: true,
            allowSkip: true,
            allowReorder: false,
            allowUpdatePayment: false,
        );
        $loader = $this->buildLoader(subscriptionSettings: $settings);

        $page = $loader->load(new Request(), new FakeSalesChannelContext());

        self::assertTrue($page->isAllowAddressEditing());
        self::assertTrue($page->isAllowPauseResume());
        self::assertTrue($page->isAllowSkip());
        self::assertFalse($page->isAllowReorder());
        self::assertFalse($page->isAllowUpdatePayment());
    }

    public function testTheStorefrontSeesThePriceUpdateNotice(): void
    {
        $settings = new SubscriptionSettings(enabled: true, priceUpdateMode: SubscriptionSettings::PRICE_UPDATE_MODE_AUTO, priceUpdateNoticeDays: 14);
        $loader = $this->buildLoader(subscriptionSettings: $settings);

        $page = $loader->load(new Request(), new FakeSalesChannelContext());

        self::assertSame(14, $page->getPriceUpdateNoticeDays());
        self::assertTrue($page->isAutoPriceUpdate());
    }

    private function buildLoader(
        ?MetaInformation $metaInformation = null,
        ?SubscriptionCollection $subscriptions = null,
        ?SubscriptionSettings $subscriptionSettings = null,
    ): SubscriptionPageLoader {
        return new SubscriptionPageLoader(
            new FakeGenericPageLoader($metaInformation),
            new FakeListSubscriptionsRoute($subscriptions ?? new SubscriptionCollection()),
            $this->countryRoute,
            new FakeSalutationRoute(new SalutationCollection([$this->salutation()])),
            new FakeSettingsService(subscriptionSettings: $subscriptionSettings ?? new SubscriptionSettings(enabled: true)),
        );
    }

    private function country(): CountryEntity
    {
        $country = new CountryEntity();
        $country->setId('country-id');
        $country->setIso('DE');

        return $country;
    }

    private function salutation(): SalutationEntity
    {
        $salutation = new SalutationEntity();
        $salutation->setId('salutation-id');

        return $salutation;
    }
}
