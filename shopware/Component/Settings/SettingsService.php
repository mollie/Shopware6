<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings;

use Mollie\Shopware\Component\Settings\Struct\AccountSettings;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\ApplePaySettings;
use Mollie\Shopware\Component\Settings\Struct\CreditCardSettings;
use Mollie\Shopware\Component\Settings\Struct\EnvironmentSettings;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Settings\Struct\PayPalExpressSettings;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SettingsService extends AbstractSettingsService
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config';
    private const CACHE_KEY_MOLLIE = 'mollie';
    private const CACHE_KEY_SHOPWARE = 'shopware';

    /**
     * @var array<string, ApiSettings|LoggerSettings|mixed|PaymentSettings>
     */
    private array $settingsCache = [];

    public function __construct(
        private SystemConfigService $systemConfigService,
        #[Autowire('%env(bool:default:false:MOLLIE_DEV_MODE)')]
        private bool $devMode = false,
        #[Autowire('%env(bool:default:false:MOLLIE_CYPRESS_MODE)')]
        private bool $cypressMode = false,
        #[Autowire('%env(bool:default:false:MOLLIE_PAYPAL_EXPRESS_BETA)')]
        private bool $paypalExpressEanbled = false,
        #[Autowire('%env(int:default:1:MOLLIE_PAYPAL_EXPRESS_BUTTON_STYLE)')]
        private string $paypalExpressStyle = '1',
        #[Autowire('%env(int:default:1MOLLIE_PAYPAL_EXPRESS_BUTTON_SHAPE)')]
        private string $paypalExpressShape = '1',
        #[Autowire('%env(string:default:"":MOLLIE_PAYPAL_EXPRESS_BUTTON_RESTRICTIONS)')]
        private string $paypalExpressRestrictions = ''
    ) {
    }

    public function getDecorated(): AbstractSettingsService
    {
        throw new DecorationPatternException(self::class);
    }

    public function getLoggerSettings(?string $salesChannelId = null): LoggerSettings
    {
        $cacheKey = LoggerSettings::class . '_' . ($salesChannelId ?? 'all');

        if (isset($this->settingsCache[$cacheKey])) {
            return $this->settingsCache[$cacheKey];
        }

        $shopwareSettings = $this->getMollieSettings($salesChannelId);
        $settings = LoggerSettings::createFromShopwareArray($shopwareSettings);
        $this->settingsCache[$cacheKey] = $settings;

        return $settings;
    }

    public function getPaypalExpressSettings(): PayPalExpressSettings
    {
        $settings = new PayPalExpressSettings($this->paypalExpressEanbled);
        $settings->setStyle((int) $this->paypalExpressStyle);
        $settings->setShape((int) $this->paypalExpressShape);
        $settings->setRestrictions(explode(' ', trim($this->paypalExpressRestrictions)));

        return $settings;
    }

    public function getEnvironmentSettings(): EnvironmentSettings
    {
        return new EnvironmentSettings($this->devMode, $this->cypressMode);
    }

    public function getApiSettings(?string $salesChannelId = null): ApiSettings
    {
        $cacheKey = ApiSettings::class . '_' . ($salesChannelId ?? 'all');

        if (isset($this->settingsCache[$cacheKey])) {
            return $this->settingsCache[$cacheKey];
        }

        $shopwareSettings = $this->getMollieSettings($salesChannelId);
        $settings = ApiSettings::createFromShopwareArray($shopwareSettings);
        $this->settingsCache[$cacheKey] = $settings;

        return $settings;
    }

    public function getPaymentSettings(?string $salesChannelId = null): PaymentSettings
    {
        $cacheKey = PaymentSettings::class . '_' . ($salesChannelId ?? 'all');

        if (isset($this->settingsCache[$cacheKey])) {
            return $this->settingsCache[$cacheKey];
        }

        $shopwareSettings = $this->getMollieSettings($salesChannelId);
        $settings = PaymentSettings::createFromShopwareArray($shopwareSettings);
        $this->settingsCache[$cacheKey] = $settings;

        return $settings;
    }

    public function getCreditCardSettings(?string $salesChannelId = null): CreditCardSettings
    {
        $cacheKey = CreditCardSettings::class . '_' . ($salesChannelId ?? 'all');
        if (isset($this->settingsCache[$cacheKey])) {
            return $this->settingsCache[$cacheKey];
        }
        $shopwareSettings = $this->getMollieSettings($salesChannelId);

        $settings = CreditCardSettings::createFromShopwareArray($shopwareSettings);
        $this->settingsCache[$cacheKey] = $settings;

        return $settings;
    }

    public function getAccountSettings(?string $salesChannelId = null): AccountSettings
    {
        $cacheKey = AccountSettings::class . '_' . ($salesChannelId ?? 'all');
        if (isset($this->settingsCache[$cacheKey])) {
            return $this->settingsCache[$cacheKey];
        }
        $shopwareSettings = $this->getShopwareSettings($salesChannelId);
        $settings = AccountSettings::createFromShopwareArray($shopwareSettings);
        $this->settingsCache[$cacheKey] = $settings;

        return $settings;
    }

    public function getApplePaySettings(?string $salesChannelId = null): ApplePaySettings
    {
        $cacheKey = ApplePaySettings::class . '_' . ($salesChannelId ?? 'all');
        if (isset($this->settingsCache[$cacheKey])) {
            return $this->settingsCache[$cacheKey];
        }
        $shopwareSettings = $this->getMollieSettings($salesChannelId);

        $settings = ApplePaySettings::createFromShopwareArray($shopwareSettings);
        $this->settingsCache[$cacheKey] = $settings;

        return $settings;
    }

    /**
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws \Psr\Container\ContainerExceptionInterface
     *
     * @return array<mixed[]>
     */
    private function getMollieSettings(?string $salesChannelId = null): array
    {
        $cacheKey = self::CACHE_KEY_MOLLIE . '_' . ($salesChannelId ?? 'all');

        if (isset($this->settingsCache[$cacheKey])) {
            return $this->settingsCache[$cacheKey];
        }

        $shopwareSettingsArray = $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN, $salesChannelId);
        if (! is_array($shopwareSettingsArray)) {
            return [];
        }
        $this->settingsCache[$cacheKey] = $shopwareSettingsArray;

        return $shopwareSettingsArray;
    }

    /**
     * @return array<mixed[]>
     */
    private function getShopwareSettings(?string $salesChannelId = null): array
    {
        $cacheKey = self::CACHE_KEY_SHOPWARE . '_' . ($salesChannelId ?? 'all');

        if (isset($this->settingsCache[$cacheKey])) {
            return $this->settingsCache[$cacheKey];
        }

        $shopwareSettingsArray = $this->systemConfigService->get('core', $salesChannelId);

        if (! is_array($shopwareSettingsArray)) {
            return [];
        }
        $this->settingsCache[$cacheKey] = $shopwareSettingsArray;

        return $shopwareSettingsArray;
    }
}
