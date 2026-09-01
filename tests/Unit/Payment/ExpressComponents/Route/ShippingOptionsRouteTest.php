<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\ShippingOption;
use Mollie\Shopware\Component\Mollie\ShippingOptionCollection;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\ShippingOptionsRoute;
use Mollie\Shopware\Component\Settings\Struct\ExpressComponentsSettings;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContextService;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeShippingOptionsResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Mollie calls this server to server whenever the shopper picks another address inside the express
 * component. It cannot send credentials and the payload only carries the session id, so the sales
 * channel and the cart token come from the url instead.
 */
#[CoversClass(ShippingOptionsRoute::class)]
final class ShippingOptionsRouteTest extends TestCase
{
    private const SALES_CHANNEL_ID = 'sales-channel-id';
    private const CART_TOKEN = 'cart-token';

    private FakeShippingOptionsResolver $shippingOptionsResolver;
    private FakeSalesChannelContextService $salesChannelContextService;

    protected function setUp(): void
    {
        $shippingOptions = new ShippingOptionCollection();
        $shippingOptions->add(new ShippingOption('Express', 'shipping-method-id', new Money(3.99, 'EUR')));

        $this->shippingOptionsResolver = new FakeShippingOptionsResolver($shippingOptions);
        $this->salesChannelContextService = new FakeSalesChannelContextService(new FakeSalesChannelContext(self::SALES_CHANNEL_ID, self::CART_TOKEN));
    }

    public function testTheResolvedShippingOptionsAreAnsweredToMollie(): void
    {
        $response = $this->route(enabled: true)->shippingOptions(self::SALES_CHANNEL_ID, self::CART_TOKEN, $this->callbackRequest('NL'));

        $this->assertCount(1, $response->getShippingOptions());
        $this->assertSame('Express', $response->getShippingOptions()->first()?->getDescription());
    }

    public function testTheAddressOfTheCallbackReachesTheResolver(): void
    {
        $this->route(enabled: true)->shippingOptions(self::SALES_CHANNEL_ID, self::CART_TOKEN, $this->callbackRequest('NL', '1234AB', 'Amsterdam', 'Noord-Holland'));

        $address = $this->shippingOptionsResolver->getLastAddress();
        $this->assertSame('NL', $address->getCountry());
        $this->assertSame('1234AB', $address->getPostalCode());
        $this->assertSame('Amsterdam', $address->getCity());
        $this->assertSame('Noord-Holland', $address->getRegion());
    }

    /**
     * The cart the options are priced for is not derivable from the payload, so the context is
     * rebuilt for the sales channel and the cart token of the url.
     */
    public function testTheContextIsRebuiltForTheCartOfTheUrl(): void
    {
        $this->route(enabled: true)->shippingOptions(self::SALES_CHANNEL_ID, self::CART_TOKEN, $this->callbackRequest('NL'));

        $parameters = $this->salesChannelContextService->getLastParameters();
        $this->assertSame(self::SALES_CHANNEL_ID, $parameters->getSalesChannelId());
        $this->assertSame(self::CART_TOKEN, $parameters->getToken());
    }

    /**
     * Without a country there is nothing to price, and rebuilding the context for nothing would
     * reset the cart of the shopper.
     */
    public function testACallbackWithoutACountryIsAnsweredWithoutOptions(): void
    {
        $response = $this->route(enabled: true)->shippingOptions(self::SALES_CHANNEL_ID, self::CART_TOKEN, $this->callbackRequest(''));

        $this->assertCount(0, $response->getShippingOptions());
    }

    public function testABodyThatIsNoJsonObjectIsAnsweredWithoutOptions(): void
    {
        $response = $this->route(enabled: true)->shippingOptions(self::SALES_CHANNEL_ID, self::CART_TOKEN, new Request(content: 'not json'));

        $this->assertCount(0, $response->getShippingOptions());
    }

    public function testACallbackForADisabledSalesChannelIsRejected(): void
    {
        $this->expectException(ExpressComponentsException::class);

        $this->route(enabled: false)->shippingOptions(self::SALES_CHANNEL_ID, self::CART_TOKEN, $this->callbackRequest('NL'));
    }

    public function testACallbackWithoutACartTokenIsRejected(): void
    {
        $this->expectException(ExpressComponentsException::class);

        $this->route(enabled: true)->shippingOptions(self::SALES_CHANNEL_ID, '', $this->callbackRequest('NL'));
    }

    public function testTheRouteCannotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->route(enabled: true)->getDecorated();
    }

    private function route(bool $enabled): ShippingOptionsRoute
    {
        return new ShippingOptionsRoute(
            new FakeSettingsService(expressComponentsSettings: new ExpressComponentsSettings($enabled)),
            $this->shippingOptionsResolver,
            $this->salesChannelContextService,
            new FakeLogger()
        );
    }

    private function callbackRequest(string $country, string $postalCode = '', string $city = '', string $region = ''): Request
    {
        $body = [
            'sessionId' => 'ses_callback',
            'shippingAddress' => [
                'country' => $country,
                'postalCode' => $postalCode,
                'city' => $city,
                'region' => $region,
            ],
        ];

        return new Request(content: (string) json_encode($body));
    }
}
