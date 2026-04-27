<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestriction;
use Mollie\Shopware\Component\Payment\ExpressMethod\VisibilityRestrictionCollection;
use Mollie\Shopware\Component\Settings\Struct\ApplePaySettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApplePaySettings::class)]
final class ApplePaySettingsTest extends TestCase
{
    public function testGetters(): void
    {
        $restrictions = new VisibilityRestrictionCollection();
        $restrictions->add(VisibilityRestriction::PRODUCT_DETAIL_PAGE);

        $settings = new ApplePaySettings(true, $restrictions, ['shop.example.com']);

        $this->assertTrue($settings->isApplePayDirectEnabled());
        $this->assertSame($restrictions, $settings->getVisibilityRestrictions());
        $this->assertSame(['shop.example.com'], $settings->getAllowDomainList());
    }

    public function testCreateFromShopwareArrayEnabled(): void
    {
        $settings = ApplePaySettings::createFromShopwareArray([
            ApplePaySettings::KEY_APPLE_PAY_DIRECT_ENABLED => true,
            ApplePaySettings::KEY_RESTRICTIONS => ['pdp', 'cart'],
            ApplePaySettings::KEY_ALLOWED_DOMAIN_LIST => 'shop1.com,shop2.com',
        ]);

        $this->assertTrue($settings->isApplePayDirectEnabled());
        $this->assertSame(2, $settings->getVisibilityRestrictions()->count());
        $this->assertCount(2, $settings->getAllowDomainList());
    }

    public function testCreateFromShopwareArrayDefaults(): void
    {
        $settings = ApplePaySettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isApplePayDirectEnabled());
        $this->assertSame(0, $settings->getVisibilityRestrictions()->count());
    }
}
