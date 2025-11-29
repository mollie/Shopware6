<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Settings;

use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @infection-ignore-all
 */
final class LoggerSettingsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSettingsCanBeReadFromDatabase(): void
    {
        /**
         * @var SystemConfigService $systemConfigService
         */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $oldLogFileDays = $systemConfigService->get(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . LoggerSettings::KEY_LOG_FILE_DAYS);
        $oldDebugMode = $systemConfigService->get(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . LoggerSettings::KEY_DEBUG_MODE);

        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . LoggerSettings::KEY_LOG_FILE_DAYS, 10);
        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . LoggerSettings::KEY_DEBUG_MODE, false);

        $devMode = (bool) EnvironmentHelper::getVariable('MOLLIE_DEV_MODE', false);
        $cypressMode = (bool) EnvironmentHelper::getVariable('MOLLIE_CYPRESS_MODE', false);
        $settingsService = new SettingsService($systemConfigService, $devMode, $cypressMode);

        $actualSettings = $settingsService->getLoggerSettings();

        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . LoggerSettings::KEY_LOG_FILE_DAYS, $oldLogFileDays);
        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . LoggerSettings::KEY_DEBUG_MODE, $oldDebugMode);

        $this->assertSame(10, $actualSettings->getLogFileDays());
        $this->assertFalse($actualSettings->isDebugMode());
    }

    public function testSettingsAreCachedPerSalesChannel(): void
    {
        $settingsService = $this->getContainer()->get(SettingsService::class);

        $expectedSettings = $settingsService->getLoggerSettings();
        $actualSettings = $settingsService->getLoggerSettings();
        $differentSalesChannelSettings = $settingsService->getLoggerSettings(Uuid::randomHex());

        $this->assertSame($expectedSettings, $actualSettings);
        $this->assertNotSame($actualSettings, $differentSalesChannelSettings);
    }
}
