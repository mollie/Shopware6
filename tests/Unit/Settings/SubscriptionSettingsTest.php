<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionSettings::class)]
final class SubscriptionSettingsTest extends TestCase
{
    public function testDefaultsApplyWhenSettingsArrayIsEmpty(): void
    {
        $settings = SubscriptionSettings::createFromShopwareArray([]);

        $this->assertSame(SubscriptionSettings::PRICE_UPDATE_MODE_KEEP, $settings->getPriceUpdateMode());
        $this->assertSame(0, $settings->getPriceUpdateNoticeDays());
        $this->assertFalse($settings->isAutoPriceUpdate());
    }

    public function testExplicitValuesOverrideDefaults(): void
    {
        $settings = SubscriptionSettings::createFromShopwareArray([
            SubscriptionSettings::KEY_PRICE_UPDATE_MODE => SubscriptionSettings::PRICE_UPDATE_MODE_AUTO,
            SubscriptionSettings::KEY_PRICE_UPDATE_NOTICE_DAYS => 14,
        ]);

        $this->assertSame(SubscriptionSettings::PRICE_UPDATE_MODE_AUTO, $settings->getPriceUpdateMode());
        $this->assertSame(14, $settings->getPriceUpdateNoticeDays());
        $this->assertTrue($settings->isAutoPriceUpdate());
    }

    public function testNoticeDaysCoercedToInt(): void
    {
        $settings = SubscriptionSettings::createFromShopwareArray([
            SubscriptionSettings::KEY_PRICE_UPDATE_NOTICE_DAYS => '7',
        ]);

        $this->assertSame(7, $settings->getPriceUpdateNoticeDays());
    }
}
