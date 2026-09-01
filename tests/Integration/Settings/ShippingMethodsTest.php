<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Settings;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetShippingMethodsRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayAmount;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Struct\ApplePayShippingMethod;
use Mollie\Shopware\Integration\Data\SalesChannelTestBehaviour;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @infection-ignore-all
 */
#[CoversClass(GetShippingMethodsRoute::class)]
#[Group('core')]
final class ShippingMethodsTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelTestBehaviour;

    /**
     * Test that the route returns shipping methods for a valid country
     */
    public function testGetShippingMethodsReturnsShippingMethodsForValidCountry(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();
        $this->upsertCountry($salesChannelContext, true, true);

        $shippingMethods = $this->getShippingMethods('XXX', $salesChannelContext);

        $this->assertNotEmpty($shippingMethods);
    }

    /**
     * Test that the route throws an exception for an invalid country code
     */
    public function testGetShippingMethodsThrowsExceptionForInvalidCountry(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();

        $this->expectException(ApplePayDirectException::class);
        $this->expectExceptionMessage('Invalid country code');

        $this->getShippingMethods('ZZZZ', $salesChannelContext);
    }

    /**
     * Test that the returned shipping methods have the expected structure
     */
    public function testGetShippingMethodsReturnsCorrectStructure(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();
        $this->upsertCountry($salesChannelContext, true, true);

        $shippingMethods = $this->getShippingMethods('XXX', $salesChannelContext);

        $this->assertNotEmpty($shippingMethods);

        foreach ($shippingMethods as $method) {
            $this->assertInstanceOf(ApplePayShippingMethod::class, $method);
            $this->assertNotEmpty($method->getIdentifier());
            $this->assertNotEmpty($method->getLabel());
            $this->assertInstanceOf(ApplePayAmount::class, $method->getAmount());
        }
    }

    /**
     * Test that the route throws an exception when shipping is not available for the country
     */
    public function testGetShippingMethodsReturnsExceptionWhenShippingNotAvailableForCountry(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();
        $this->upsertCountry($salesChannelContext, true, false);

        $this->expectException(ApplePayDirectException::class);
        $this->expectExceptionMessage('Invalid country code');

        $this->getShippingMethods('XXX', $salesChannelContext);
    }

    /**
     * Test that the route throws an exception when the country is inactive
     */
    public function testGetShippingMethodsReturnsExceptionWhenCountryIsInactive(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();
        $this->upsertCountry($salesChannelContext, false, true);

        $this->expectException(ApplePayDirectException::class);
        $this->expectExceptionMessage('Invalid country code');

        $this->getShippingMethods('XXX', $salesChannelContext);
    }

    /**
     * @return ApplePayShippingMethod[]
     */
    private function getShippingMethods(string $countryCode, SalesChannelContext $salesChannelContext): array
    {
        /** @var GetShippingMethodsRoute $route */
        $route = $this->getContainer()->get(GetShippingMethodsRoute::class);

        $request = new Request([], ['countryCode' => $countryCode]);

        return $route->methods($request, $salesChannelContext)->getShippingMethods();
    }

    private function upsertCountry(SalesChannelContext $salesChannelContext, bool $active, bool $shippingAvailable): void
    {
        /** @var EntityRepository $countryRepository */
        $countryRepository = $this->getContainer()->get('country.repository');

        $countryRepository->upsert([[
            'id' => Uuid::fromStringToHex('country-xxx'),
            'iso' => 'XXX',
            'active' => $active,
            'name' => 'Testland',
            'shippingAvailable' => $shippingAvailable,
            'salesChannels' => [
                ['id' => $salesChannelContext->getSalesChannelId()],
            ],
        ]], $salesChannelContext->getContext());
    }
}
