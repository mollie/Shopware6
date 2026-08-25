<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings\Struct;

use Mollie\Shopware\Component\Settings\Struct\ApplePaySettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApplePaySettings::class)]
final class ApplePaySettingsTest extends TestCase
{
    public function testApplePayDirectIsOffForAShopThatNeverConfiguredIt(): void
    {
        $settings = ApplePaySettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isApplePayDirectEnabled());
        $this->assertCount(0, $settings->getVisibilityRestrictions());
    }

    public function testConfiguredPositionsBecomeVisibilityRestrictions(): void
    {
        $settings = ApplePaySettings::createFromShopwareArray([
            ApplePaySettings::KEY_APPLE_PAY_DIRECT_ENABLED => true,
            ApplePaySettings::KEY_RESTRICTIONS => ['pdp', 'cart'],
        ]);

        $this->assertTrue($settings->isApplePayDirectEnabled());
        $this->assertSame(['pdp', 'cart'], $settings->getVisibilityRestrictions()->toArray());
    }

    public function testCommaSeparatedDomainsBecomeIndividualEntries(): void
    {
        $settings = ApplePaySettings::createFromShopwareArray([
            ApplePaySettings::KEY_ALLOWED_DOMAIN_LIST => 'shop.example.com,checkout.example.com',
        ]);

        $this->assertSame(['shop.example.com', 'checkout.example.com'], $settings->getAllowDomainList());
    }

    public function testASingleConfiguredDomainIsTheOnlyAllowedOne(): void
    {
        $settings = ApplePaySettings::createFromShopwareArray([
            ApplePaySettings::KEY_ALLOWED_DOMAIN_LIST => 'shop.example.com',
        ]);

        $this->assertSame(['shop.example.com'], $settings->getAllowDomainList());
    }

    public function testSpacesAroundTheDomainsAreIgnored(): void
    {
        $settings = ApplePaySettings::createFromShopwareArray([
            ApplePaySettings::KEY_ALLOWED_DOMAIN_LIST => 'shop.example.com, checkout.example.com',
        ]);

        $this->assertSame(['shop.example.com', 'checkout.example.com'], $settings->getAllowDomainList());
    }

    public function testATrailingCommaDoesNotAddAnEmptyDomain(): void
    {
        $settings = ApplePaySettings::createFromShopwareArray([
            ApplePaySettings::KEY_ALLOWED_DOMAIN_LIST => 'shop.example.com,',
        ]);

        $this->assertSame(['shop.example.com'], $settings->getAllowDomainList());
    }

    public function testAnUnconfiguredAllowListPermitsNoDomain(): void
    {
        $settings = ApplePaySettings::createFromShopwareArray([]);

        $this->assertSame([], $settings->getAllowDomainList());
    }
}
