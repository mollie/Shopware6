<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionSettings::class)]
final class SubscriptionSettingsTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $settings = new SubscriptionSettings();

        $this->assertFalse($settings->isEnabled());
        $this->assertFalse($settings->isShowIndicator());
        $this->assertFalse($settings->isAllowEditAddress());
        $this->assertFalse($settings->isAllowPauseAndResume());
        $this->assertFalse($settings->isAllowSkip());
        $this->assertFalse($settings->isSkipIfFailed());
        $this->assertSame(0, $settings->getReminderDays());
        $this->assertSame(0, $settings->getCancelDays());
    }

    public function testCreateFromShopwareArray(): void
    {
        $settings = SubscriptionSettings::createFromShopwareArray([
            SubscriptionSettings::KEY_ENABLED => true,
            SubscriptionSettings::KEY_SHOW_INDICATOR => true,
            SubscriptionSettings::KEY_ALLOW_EDIT_ADDRESS => true,
            SubscriptionSettings::KEY_ALLOW_PAUSE_RESUME => true,
            SubscriptionSettings::KEY_ALLOW_SKIP => true,
            SubscriptionSettings::KEY_SKIP_IF_FAILED => true,
            SubscriptionSettings::KEY_REMINDER_DAYS => 7,
            SubscriptionSettings::KEY_CANCEL_DAYS => 30,
        ]);

        $this->assertTrue($settings->isEnabled());
        $this->assertTrue($settings->isShowIndicator());
        $this->assertTrue($settings->isAllowEditAddress());
        $this->assertTrue($settings->isAllowPauseAndResume());
        $this->assertTrue($settings->isAllowSkip());
        $this->assertTrue($settings->isSkipIfFailed());
        $this->assertSame(7, $settings->getReminderDays());
        $this->assertSame(30, $settings->getCancelDays());
    }

    public function testCreateFromShopwareArrayWithDefaults(): void
    {
        $settings = SubscriptionSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isEnabled());
        $this->assertSame(0, $settings->getReminderDays());
        $this->assertSame(0, $settings->getCancelDays());
    }
}
