<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings;

use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Component\Settings\Struct\EnvironmentSettings;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class SettingsService extends AbstractSettingsService
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config';
    private const CACHE_KEY_SHOPWARE = 'shopware';

    /**
     * @var array<string, ApiSettings|LoggerSettings|mixed|PaymentSettings>
     */
    private array $settingsCache = [];

    public function __construct(private SystemConfigService $systemConfigService, private bool $devMode, private bool $cypressMode)
    {
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

        $shopwareSettings = $this->getShopwareSettings($salesChannelId);
        $settings = LoggerSettings::createFromShopwareArray($shopwareSettings);
        $this->settingsCache[$cacheKey] = $settings;

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

        $shopwareSettings = $this->getShopwareSettings($salesChannelId);
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

        $shopwareSettings = $this->getShopwareSettings($salesChannelId);
        $settings = PaymentSettings::createFromShopwareArray($shopwareSettings);
        $this->settingsCache[$cacheKey] = $settings;

        return $settings;
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     *
     * @return array<mixed[]>
     */
    private function getShopwareSettings(?string $salesChannelId = 'all'): array
    {
        $cacheKey = self::CACHE_KEY_SHOPWARE . '_' . $salesChannelId;

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
}
