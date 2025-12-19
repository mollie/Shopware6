<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Logger;

use Mollie\Shopware\Component\Mollie\Mode;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\Struct\AccountSettings;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\ApplePaySettings;
use Mollie\Shopware\Component\Settings\Struct\CreditCardSettings;
use Mollie\Shopware\Component\Settings\Struct\EnvironmentSettings;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use Mollie\Shopware\Component\Settings\Struct\OrderStateSettings;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Settings\Struct\PayPalExpressSettings;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;

final class FakeSettingsService extends AbstractSettingsService
{
    public function __construct(private ?LoggerSettings $loggerSettings = null,
        private ?PaymentSettings $paymentSettings = null,
        private ?ApiSettings $apiSettings = null,
        private ?string $profileId = null,
    ) {
        if ($this->loggerSettings === null) {
            $this->loggerSettings = new LoggerSettings(true, 0);
        }
        if ($this->paymentSettings === null) {
            $this->paymentSettings = new PaymentSettings('',0);
        }
        if ($this->apiSettings === null) {
            $profileId = $this->profileId ?? '';
            $this->apiSettings = new ApiSettings('test_key', 'live_key', Mode::TEST, $profileId);
        }
    }

    public function getPaypalExpressSettings(?string $salesChannelId = null): PayPalExpressSettings
    {
        // TODO: Implement getPaypalExpressSettings() method.
    }

    public function getEnvironmentSettings(): EnvironmentSettings
    {
        // TODO: Implement getEnvironmentSettings() method.
    }

    public function getDecorated(): AbstractSettingsService
    {
        // TODO: Implement getDecorated() method.
    }

    public function getCreditCardSettings(?string $salesChannelId = null): CreditCardSettings
    {
        // TODO: Implement getCreditCardSettings() method.
    }

    public function getApiSettings(?string $salesChannelId = null): ApiSettings
    {
        return $this->apiSettings;
    }

    public function getPaymentSettings(?string $salesChannelId = null): PaymentSettings
    {
        return $this->paymentSettings;
    }

    public function getLoggerSettings(?string $salesChannelId = null): LoggerSettings
    {
        return $this->loggerSettings;
    }

    public function getAccountSettings(?string $salesChannelId = null): AccountSettings
    {
        // TODO: Implement getAccountSettings() method.
    }

    public function getSubscriptionSettings(?string $salesChannelId = null): SubscriptionSettings
    {
        // TODO: Implement getSubscriptionSettings() method.
    }

    public function getApplePaySettings(?string $salesChannelId = null): ApplePaySettings
    {
        // TODO: Implement getApplePaySettings() method.
    }

    public function getOrderStateSettings(?string $salesChannelId = null): OrderStateSettings
    {
        // TODO: Implement getOrderStateSettings() method.
    }
}
