<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings;

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

abstract class AbstractSettingsService
{
    abstract public function getDecorated(): AbstractSettingsService;

    abstract public function getLoggerSettings(?string $salesChannelId = null): LoggerSettings;

    abstract public function getApiSettings(?string $salesChannelId = null): ApiSettings;

    abstract public function getPaymentSettings(?string $salesChannelId = null): PaymentSettings;

    abstract public function getEnvironmentSettings(): EnvironmentSettings;

    abstract public function getPaypalExpressSettings(?string $salesChannelId = null): PayPalExpressSettings;

    abstract public function getCreditCardSettings(?string $salesChannelId = null): CreditCardSettings;

    abstract public function getAccountSettings(?string $salesChannelId = null): AccountSettings;

    abstract public function getApplePaySettings(?string $salesChannelId = null): ApplePaySettings;

    abstract public function getSubscriptionSettings(?string $salesChannelId = null): SubscriptionSettings;

    abstract public function getOrderStateSettings(?string $salesChannelId = null): OrderStateSettings;
}
