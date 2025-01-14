<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings;

use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class SettingsService extends AbstractSettingsService
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config';
    private const CACHE_KEY_LOGGER = 'logger';
    private const CACHE_KEY_SHOPWARE = 'shopware';

    private SystemConfigService $systemConfigService;

    private array $settingsCache = [];



    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractSettingsService
    {
        throw new DecorationPatternException(self::class);
    }

    public function getLoggerSettings(?string $salesChannelId = null): LoggerSettings
    {
        $cacheKey = self::CACHE_KEY_LOGGER . '_' . ($salesChannelId ?? 'all');

        if (isset($this->settingsCache[$cacheKey])) {
            return $this->settingsCache[$cacheKey];
        }


        $shopwareSettings = $this->getShopwareSettings($salesChannelId);
        $loggerSettings = LoggerSettings::createFromShopwareArray($shopwareSettings);
        $this->settingsCache[$cacheKey] = $loggerSettings;

        return $loggerSettings;
    }

    private function getShopwareSettings(?string $salesChannelId = null): array
    {
        $cacheKey = self::CACHE_KEY_SHOPWARE . '_' . ($salesChannelId ?? 'all');

        if (isset($this->settingsCache[$cacheKey])) {
            return $this->settingsCache[$cacheKey];
        }

        $shopwareSettingsArray = $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN, $salesChannelId);
        $this->settingsCache[$cacheKey] = $shopwareSettingsArray;
        return $shopwareSettingsArray;
    }
}
