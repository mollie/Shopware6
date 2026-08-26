<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect\Route;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetShippingMethodsResponse;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetShippingMethodsRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayShippingMethod;
use Mollie\Shopware\Unit\Fake\FakeContextSwitchRoute;
use Mollie\Shopware\Unit\Fake\FakeCountryRepository;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContextService;
use Mollie\Shopware\Unit\Payment\ApplePayDirect\Fake\FakeGetCartRoute;
use Mollie\Shopware\Unit\Payment\ApplePayDirect\Fake\FakeSetShippingMethodRoute;
use Mollie\Shopware\Unit\Payment\ApplePayDirect\Fake\FakeShippingMethodRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\HttpFoundation\Request;

/**
 * The Apple Pay sheet calls this as soon as the shopper picks a delivery country. Every option it
 * gets back has to carry the price that country actually costs - the shopper approves that number,
 * and Shopware charges it afterwards.
 */
#[CoversClass(GetShippingMethodsRoute::class)]
final class GetShippingMethodsRouteTest extends TestCase
{
    private const SELECTED = 'shipping-method-standard';
    private const OTHER = 'shipping-method-express';

    public function testEveryAvailableShippingMethodIsOffered(): void
    {
        $methods = $this->methods()->getShippingMethods();

        $this->assertSame(
            [self::SELECTED, self::OTHER],
            array_map(fn ($method) => $method->getIdentifier(), $methods)
        );
    }

    /**
     * Each option is priced by putting it into the context and asking the cart route - a shared
     * price would charge the shopper the wrong shipping.
     */
    public function testEveryOptionCarriesItsOwnShippingCosts(): void
    {
        $methods = $this->methods(shippingCosts: [self::SELECTED => 4.99, self::OTHER => 9.99])->getShippingMethods();

        $this->assertSame(4.99, $methods[0]->getAmount()->getValue());
        $this->assertSame(9.99, $methods[1]->getAmount()->getValue());
    }

    /**
     * Apple preselects the first entry, so the method the shopper already had in the shop has to
     * be first - otherwise opening the sheet silently changes their delivery option.
     */
    public function testTheMethodTheShopperAlreadyChoseComesFirst(): void
    {
        $methods = $this->methods(selected: self::OTHER)->getShippingMethods();

        $this->assertSame(self::OTHER, $methods[0]->getIdentifier());
        $this->assertSame(self::SELECTED, $methods[1]->getIdentifier());
    }

    public function testTheNameAndDeliveryTimeAreWhatTheSheetShows(): void
    {
        $methods = $this->methods()->getShippingMethods();

        $this->assertSame('Standard', $methods[0]->getLabel());
        $this->assertSame('1-3 days', $methods[0]->getDetail());
    }

    /**
     * A shipping method without a delivery time still has to be offered; the sheet just shows no
     * subtitle for it.
     */
    public function testAMethodWithoutADeliveryTimeIsStillOffered(): void
    {
        $methods = $this->methods(withDeliveryTime: false)->getShippingMethods();

        $this->assertSame('', $methods[0]->getDetail());
    }

    public function testTheShippingCountryIsSwitchedIntoTheContext(): void
    {
        $contextSwitchRoute = new FakeContextSwitchRoute();

        $this->methods(contextSwitchRoute: $contextSwitchRoute);

        $this->assertSame('country-de', $contextSwitchRoute->getLastSwitch()[SalesChannelContextService::COUNTRY_ID]);
    }

    /**
     * Only methods that are actually available for that country may be offered.
     */
    public function testOnlyMethodsAvailableForThatCountryAreAsked(): void
    {
        $shippingMethodRoute = new FakeShippingMethodRoute($this->shippingMethods());

        $this->methods(shippingMethodRoute: $shippingMethodRoute);

        $this->assertSame('1', $shippingMethodRoute->getLastRequest()->query->get('onlyAvailable'));
    }

    /**
     * Pricing the options leaves the shopper's own choice selected in the context; the sheet is
     * only a preview until they confirm.
     */
    public function testTheShoppersOwnChoiceIsSelectedAgainAtTheEnd(): void
    {
        $setShippingMethodRoute = new FakeSetShippingMethodRoute();

        $this->methods(setShippingMethodRoute: $setShippingMethodRoute);

        $selected = $setShippingMethodRoute->getSelectedShippingMethodIds();
        $this->assertSame(self::SELECTED, end($selected));
    }

    /**
     * Without a country there is nothing to price, and answering with an empty list would leave
     * the sheet spinning.
     */
    public function testARequestWithoutACountryIsRejected(): void
    {
        $this->expectException(ApplePayDirectException::class);

        $this->route()->methods(new Request(), $this->context());
    }

    /**
     * A country the shop does not deliver to has no shipping methods; the shopper has to be told
     * instead of getting an empty sheet.
     */
    public function testACountryTheShopDoesNotDeliverToIsRejected(): void
    {
        $this->expectException(ApplePayDirectException::class);

        $this->route(countries: [])->methods($this->request('XX'), $this->context());
    }

    /**
     * The method the customer chose in the shop does not have to be available for the country they
     * pick in the sheet. Before the guard in setSelectedMethodToFirstElement() the missing key was
     * read anyway and a null was pushed in front of the list that goes to Apple.
     */
    public function testAShippingMethodThatIsNotAvailableForThatCountryDoesNotBreakTheList(): void
    {
        $methods = $this->methods(selected: 'shipping-method-not-available-here')->getShippingMethods();

        $this->assertContainsOnlyInstancesOf(ApplePayShippingMethod::class, $methods);
        $this->assertSame(
            [self::SELECTED, self::OTHER],
            array_map(fn (ApplePayShippingMethod $method) => $method->getIdentifier(), $methods)
        );
    }

    /**
     * A country the shop has methods for but none that apply to this cart answers with an empty
     * list, so the sheet says "no delivery options" instead of showing a broken entry.
     */
    public function testACountryWithoutAnyApplicableMethodAnswersWithAnEmptyList(): void
    {
        $route = $this->route(shippingMethodRoute: new FakeShippingMethodRoute(new ShippingMethodCollection()));

        $methods = $route->methods($this->request(), $this->context())->getShippingMethods();

        $this->assertSame([], $methods);
    }

    private function request(string $countryCode = 'DE'): Request
    {
        return new Request([], ['countryCode' => $countryCode]);
    }

    private function context(string $selected = self::SELECTED): FakeSalesChannelContext
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($selected);

        $context = new FakeSalesChannelContext('sc-1', 'token-1');
        $context->setShippingMethod($shippingMethod);

        return $context;
    }

    /**
     * @param array<string, float> $shippingCosts
     */
    private function methods(
        string $selected = self::SELECTED,
        array $shippingCosts = [],
        bool $withDeliveryTime = true,
        ?FakeContextSwitchRoute $contextSwitchRoute = null,
        ?FakeShippingMethodRoute $shippingMethodRoute = null,
        ?FakeSetShippingMethodRoute $setShippingMethodRoute = null,
    ): GetShippingMethodsResponse {
        $route = $this->route(
            shippingCosts: $shippingCosts,
            withDeliveryTime: $withDeliveryTime,
            contextSwitchRoute: $contextSwitchRoute,
            shippingMethodRoute: $shippingMethodRoute,
            setShippingMethodRoute: $setShippingMethodRoute,
        );

        return $route->methods($this->request(), $this->context($selected));
    }

    /**
     * @param array<string, float> $shippingCosts
     * @param null|list<CountryEntity> $countries
     */
    private function route(
        array $shippingCosts = [],
        bool $withDeliveryTime = true,
        ?array $countries = null,
        ?FakeContextSwitchRoute $contextSwitchRoute = null,
        ?FakeShippingMethodRoute $shippingMethodRoute = null,
        ?FakeSetShippingMethodRoute $setShippingMethodRoute = null,
    ): GetShippingMethodsRoute {
        $germany = new CountryEntity();
        $germany->setId('country-de');

        return new GetShippingMethodsRoute(
            $shippingMethodRoute ?? new FakeShippingMethodRoute($this->shippingMethods($withDeliveryTime)),
            $setShippingMethodRoute ?? new FakeSetShippingMethodRoute(),
            new FakeSalesChannelContextService($this->context()),
            $contextSwitchRoute ?? new FakeContextSwitchRoute(),
            new FakeGetCartRoute($shippingCosts),
            new FakeEntityRepository(new CustomerAddressDefinition()),
            new FakeCountryRepository($countries ?? [$germany]),
            new NullLogger()
        );
    }

    private function shippingMethods(bool $withDeliveryTime = true): ShippingMethodCollection
    {
        return new ShippingMethodCollection([
            $this->shippingMethod(self::SELECTED, 'Standard', $withDeliveryTime ? '1-3 days' : null),
            $this->shippingMethod(self::OTHER, 'Express', $withDeliveryTime ? 'next day' : null),
        ]);
    }

    private function shippingMethod(string $id, string $name, ?string $deliveryTime): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($id);
        $shippingMethod->setName($name);

        if ($deliveryTime !== null) {
            $deliveryTimeEntity = new DeliveryTimeEntity();
            $deliveryTimeEntity->setId('delivery-time-' . $id);
            $deliveryTimeEntity->setName($deliveryTime);
            $shippingMethod->setDeliveryTime($deliveryTimeEntity);
        }

        return $shippingMethod;
    }
}
