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

    /**
     * These flags decide which buttons a customer sees on their subscription. A renamed key would
     * silently take a feature away from every shop.
     */
    public function testEverySettingIsReadFromItsConfigurationKey(): void
    {
        $settings = SubscriptionSettings::createFromShopwareArray([
            SubscriptionSettings::KEY_ENABLED => true,
            SubscriptionSettings::KEY_SHOW_INDICATOR => true,
            SubscriptionSettings::KEY_ALLOW_EDIT_ADDRESS => true,
            SubscriptionSettings::KEY_ALLOW_PAUSE_RESUME => true,
            SubscriptionSettings::KEY_ALLOW_SKIP => true,
            SubscriptionSettings::KEY_ALLOW_REORDER => false,
            SubscriptionSettings::KEY_ALLOW_UPDATE_PAYMENT => false,
            SubscriptionSettings::KEY_SKIP_IF_FAILED => true,
            SubscriptionSettings::KEY_REMINDER_DAYS => 5,
            SubscriptionSettings::KEY_CANCEL_DAYS => 3,
        ]);

        $this->assertTrue($settings->isEnabled());
        $this->assertTrue($settings->isShowIndicator());
        $this->assertTrue($settings->isAllowEditAddress());
        $this->assertTrue($settings->isAllowPauseAndResume());
        $this->assertTrue($settings->isAllowSkip());
        $this->assertFalse($settings->isAllowReorder());
        $this->assertFalse($settings->isAllowUpdatePayment());
        $this->assertTrue($settings->isSkipIfFailed());
        $this->assertSame(5, $settings->getReminderDays());
        $this->assertSame(3, $settings->getCancelDays());
    }

    /**
     * Reorder and payment update are on unless the merchant switches them off - taking them away
     * from every shop that never opened the subscription settings would be a regression.
     */
    public function testReorderAndPaymentUpdateAreOnByDefault(): void
    {
        $settings = SubscriptionSettings::createFromShopwareArray([]);

        $this->assertTrue($settings->isAllowReorder());
        $this->assertTrue($settings->isAllowUpdatePayment());
    }

    public function testSubscriptionsAreOffUntilTheMerchantSwitchesThemOn(): void
    {
        $settings = SubscriptionSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isEnabled());
        $this->assertFalse($settings->isShowIndicator());
        $this->assertFalse($settings->isAllowEditAddress());
        $this->assertFalse($settings->isAllowPauseAndResume());
        $this->assertFalse($settings->isAllowSkip());
        $this->assertFalse($settings->isSkipIfFailed());
        $this->assertSame(0, $settings->getReminderDays());
        $this->assertSame(0, $settings->getCancelDays());
    }
}
