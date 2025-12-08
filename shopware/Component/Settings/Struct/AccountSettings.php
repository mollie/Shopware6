<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class AccountSettings extends Struct
{
    public function __construct(private bool $phoneFieldShown,
        private bool $phoneFieldRequired,
        private bool $birthdayFieldShown,
        private bool $birthdayFieldRequired,
        private bool $dataProtectionEnabled,
        private bool $customerBoundToSalesChannel)
    {
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $phoneFieldIsShown = $settings['loginRegistration']['showPhoneNumberField'] ?? false;
        $phoneFieldIsRequired = $settings['loginRegistration']['phoneNumberFieldRequired'] ?? false;

        $birthdayFieldIsShown = $settings['loginRegistration']['showBirthdayField'] ?? false;
        $birthdayFieldIsRequired = $settings['loginRegistration']['birthdayFieldRequired'] ?? false;

        $dataProtectionIsEnabled = $settings['loginRegistration']['requireDataProtectionCheckbox'] ?? false;

        $customerIsBoundToSalesChannel = $settings['systemWideLoginRegistration']['isCustomerBoundToSalesChannel'] ?? false;

        return new self((bool) $phoneFieldIsShown, (bool) $phoneFieldIsRequired, (bool) $birthdayFieldIsShown, (bool) $birthdayFieldIsRequired, (bool) $dataProtectionIsEnabled, (bool) $customerIsBoundToSalesChannel);
    }

    public function isPhoneFieldShown(): bool
    {
        return $this->phoneFieldShown;
    }

    public function isPhoneFieldRequired(): bool
    {
        return $this->phoneFieldRequired;
    }

    public function isBirthdayFieldShown(): bool
    {
        return $this->birthdayFieldShown;
    }

    public function isBirthdayFieldRequired(): bool
    {
        return $this->birthdayFieldRequired;
    }

    public function isDataProtectionEnabled(): bool
    {
        return $this->dataProtectionEnabled;
    }

    public function isCustomerBoundToSalesChannel(): bool
    {
        return $this->customerBoundToSalesChannel;
    }
}
