<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings;

use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class SettingsService extends AbstractSettingsService
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config';
    private const CACHE_KEY_LOGGER = 'logger';
    private const CACHE_KEY_SHOPWARE = 'shopware';

    private ?SystemConfigService $systemConfigService  = null;

    private array $settingsCache = [];
    private ContainerInterface $container;


    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
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
        /**
         * Attention, we have to use service locator here, because in Shopware 6.4 there is an issue with system config service.
         */
        if ($this->systemConfigService === null) {
            $this->systemConfigService =  $this->container->get(SystemConfigService::class);
        }
        $shopwareSettingsArray = $this->systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN, $salesChannelId);
        $this->settingsCache[$cacheKey] = $shopwareSettingsArray;
        return $shopwareSettingsArray;
    }
}
