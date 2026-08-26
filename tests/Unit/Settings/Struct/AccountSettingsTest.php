<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings\Struct;

use Mollie\Shopware\Component\Settings\Struct\AccountSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountSettings::class)]
final class AccountSettingsTest extends TestCase
{
    public function testEveryShopwareAccountOptionReachesItsGetter(): void
    {
        $settings = AccountSettings::createFromShopwareArray([
            'loginRegistration' => [
                'showPhoneNumberField' => true,
                'phoneNumberFieldRequired' => true,
                'showBirthdayField' => true,
                'birthdayFieldRequired' => true,
                'requireDataProtectionCheckbox' => true,
            ],
            'systemWideLoginRegistration' => [
                'isCustomerBoundToSalesChannel' => true,
            ],
        ]);

        $this->assertTrue($settings->isPhoneFieldShown());
        $this->assertTrue($settings->isPhoneFieldRequired());
        $this->assertTrue($settings->isBirthdayFieldShown());
        $this->assertTrue($settings->isBirthdayFieldRequired());
        $this->assertTrue($settings->isDataProtectionEnabled());
        $this->assertTrue($settings->isCustomerBoundToSalesChannel());
    }

    public function testEveryOptionIsOffWhenShopwareReportsNoAccountConfiguration(): void
    {
        $settings = AccountSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isPhoneFieldShown());
        $this->assertFalse($settings->isPhoneFieldRequired());
        $this->assertFalse($settings->isBirthdayFieldShown());
        $this->assertFalse($settings->isBirthdayFieldRequired());
        $this->assertFalse($settings->isDataProtectionEnabled());
        $this->assertFalse($settings->isCustomerBoundToSalesChannel());
    }

    public function testAPartiallyFilledConfigurationLeavesTheMissingOptionsOff(): void
    {
        $settings = AccountSettings::createFromShopwareArray([
            'loginRegistration' => [
                'showPhoneNumberField' => true,
            ],
        ]);

        $this->assertTrue($settings->isPhoneFieldShown());
        $this->assertFalse($settings->isPhoneFieldRequired());
        $this->assertFalse($settings->isBirthdayFieldShown());
        $this->assertFalse($settings->isCustomerBoundToSalesChannel());
    }

    public function testShopwareConfigurationValuesAreCastToBool(): void
    {
        $settings = AccountSettings::createFromShopwareArray([
            'loginRegistration' => [
                'showPhoneNumberField' => '1',
                'showBirthdayField' => 0,
            ],
        ]);

        $this->assertTrue($settings->isPhoneFieldShown());
        $this->assertFalse($settings->isBirthdayFieldShown());
    }
}
