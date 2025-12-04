<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Settings;

use Kiener\MolliePayments\Components\ApplePayDirect\ApplePayDirect;
use Mollie\Shopware\Integration\Data\SalesChannelTestBehaviour;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @infection-ignore-all
 */
final class ShippingMethodsTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelTestBehaviour;

    /**
     * Test that getShippingMethods returns shipping methods for a valid country
     */
    public function testGetShippingMethodsReturnsShippingMethodsForValidCountry(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();
        $context = $salesChannelContext->getContext();

        /** @var ApplePayDirect $applePayDirect */
        $applePayDirect = $this->getContainer()->get(ApplePayDirect::class);

        /**
         * @var EntityRepository $countryRepository
         */
        $countryRepository = $this->getContainer()->get('country.repository');

        $countryId = Uuid::fromStringToHex('country-xxx');

        $countryRepository->upsert([[
            'id' => $countryId,
            'iso' => 'XXX',
            'active' => true,
            'name' => 'Testland',
            'shippingAvailable' => true,
            'salesChannels' => [
                ['id' => $salesChannelContext->getSalesChannelId()]
            ]
        ]], $context);

        $shippingMethods = $applePayDirect->getShippingMethods('XXX', $salesChannelContext);

        $this->assertNotEmpty($shippingMethods);
    }

    /**
     * Test that getShippingMethods throws exception for invalid country code
     */
    public function testGetShippingMethodsThrowsExceptionForInvalidCountry(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();

        /** @var ApplePayDirect $applePayDirect */
        $applePayDirect = $this->getContainer()->get(ApplePayDirect::class);

        $this->expectException(\Exception::class);

        $applePayDirect->getShippingMethods('ZZZZ', $salesChannelContext);
    }

    /**
     * Test that getShippingMethods returns array with expected structure
     */
    public function testGetShippingMethodsReturnsCorrectStructure(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();
        $context = $salesChannelContext->getContext();

        /** @var ApplePayDirect $applePayDirect */
        $applePayDirect = $this->getContainer()->get(ApplePayDirect::class);

        /**
         * @var EntityRepository $countryRepository
         */
        $countryRepository = $this->getContainer()->get('country.repository');

        $countryId = Uuid::fromStringToHex('country-xxx');
        $countryRepository->upsert([[
            'id' => $countryId,
            'iso' => 'XXX',
            'active' => true,
            'name' => 'Testland',
            'shippingAvailable' => true,
            'salesChannels' => [
                ['id' => $salesChannelContext->getSalesChannelId()]
            ]
        ]], $context);

        $shippingMethods = $applePayDirect->getShippingMethods('XXX', $salesChannelContext);

        $this->assertIsArray($shippingMethods);

        foreach ($shippingMethods as $method) {
            $this->assertIsArray($method);
            $this->assertArrayHasKey('identifier', $method);
            $this->assertArrayHasKey('label', $method);
            $this->assertArrayHasKey('amount', $method);
        }
    }

    /**
     * Test that getShippingMethods returns Exception when shipping_available is disabled for country
     */
    public function testGetShippingMethodsReturnsExceptionWhenShippingNotAvailableForCountry(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();
        $context = $salesChannelContext->getContext();

        /** @var ApplePayDirect $applePayDirect */
        $applePayDirect = $this->getContainer()->get(ApplePayDirect::class);

        /** @var EntityRepository $countryRepository */
        $countryRepository = $this->getContainer()->get('country.repository');

        $countryId = Uuid::fromStringToHex('country-xxx');

        $countryRepository->upsert([[
            'id' => $countryId,
            'iso' => 'XXX',
            'active' => true,
            'name' => 'Testland',
            'shippingAvailable' => false,
            'salesChannels' => [
                ['id' => $salesChannelContext->getSalesChannelId()]
            ]
        ]], $context);

        $this->expectException(\Exception::class);

        $applePayDirect->getShippingMethods('XXX', $salesChannelContext);
    }

    /**
     * Test that getShippingMethods returns Exception when country is inactive
     */
    public function testGetShippingMethodsReturnsExceptionWhenCountryIsInactive(): void
    {
        $salesChannelContext = $this->getDefaultSalesChannelContext();
        $context = $salesChannelContext->getContext();

        /** @var ApplePayDirect $applePayDirect */
        $applePayDirect = $this->getContainer()->get(ApplePayDirect::class);

        /** @var EntityRepository $countryRepository */
        $countryRepository = $this->getContainer()->get('country.repository');

        $countryId = Uuid::fromStringToHex('country-xxx');
        $countryRepository->upsert([[
            'id' => $countryId,
            'iso' => 'XXX',
            'active' => false,
            'name' => 'Testland',
            'shippingAvailable' => true,
            'salesChannels' => [
                ['id' => $salesChannelContext->getSalesChannelId()]
            ]
        ]], $context);

        $this->expectException(\Exception::class);

        $applePayDirect->getShippingMethods('XXX', $salesChannelContext);
    }
}
