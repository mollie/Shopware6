<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Settings;

use Mollie\Shopware\Component\Settings\Struct\AccountSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AccountSettings::class)]
final class AccountSettingsTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $settings = new AccountSettings(
            phoneFieldShown: true,
            phoneFieldRequired: false,
            birthdayFieldShown: true,
            birthdayFieldRequired: false,
            dataProtectionEnabled: true,
            customerBoundToSalesChannel: false
        );

        $this->assertTrue($settings->isPhoneFieldShown());
        $this->assertFalse($settings->isPhoneFieldRequired());
        $this->assertTrue($settings->isBirthdayFieldShown());
        $this->assertFalse($settings->isBirthdayFieldRequired());
        $this->assertTrue($settings->isDataProtectionEnabled());
        $this->assertFalse($settings->isCustomerBoundToSalesChannel());
    }

    public function testCreateFromShopwareArray(): void
    {
        $settings = AccountSettings::createFromShopwareArray([
            'loginRegistration' => [
                'showPhoneNumberField' => true,
                'phoneNumberFieldRequired' => true,
                'showBirthdayField' => false,
                'birthdayFieldRequired' => false,
                'requireDataProtectionCheckbox' => true,
            ],
            'systemWideLoginRegistration' => [
                'isCustomerBoundToSalesChannel' => true,
            ],
        ]);

        $this->assertTrue($settings->isPhoneFieldShown());
        $this->assertTrue($settings->isPhoneFieldRequired());
        $this->assertFalse($settings->isBirthdayFieldShown());
        $this->assertFalse($settings->isBirthdayFieldRequired());
        $this->assertTrue($settings->isDataProtectionEnabled());
        $this->assertTrue($settings->isCustomerBoundToSalesChannel());
    }

    public function testCreateFromShopwareArrayWithDefaults(): void
    {
        $settings = AccountSettings::createFromShopwareArray([]);

        $this->assertFalse($settings->isPhoneFieldShown());
        $this->assertFalse($settings->isPhoneFieldRequired());
        $this->assertFalse($settings->isBirthdayFieldShown());
        $this->assertFalse($settings->isBirthdayFieldRequired());
        $this->assertFalse($settings->isDataProtectionEnabled());
        $this->assertFalse($settings->isCustomerBoundToSalesChannel());
    }
}
