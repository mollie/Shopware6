<?php
declare(strict_types=1);

namespace Mollie\Integration\Settings;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\LoggerSettings;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @infection-ignore-all
 */
final class LoggerSettingsTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        $this->getContainer()->get(Connection::class)->setAutoCommit(true);
    }

    public function testSettingsCanBeReadFromDatabase(): void
    {
        /**
         * @var SystemConfigService $systemConfigService
         */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . LoggerSettings::KEY_LOG_FILE_DAYS, 10);
        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . LoggerSettings::KEY_DEBUG_MODE, false);

        $settingsService = new SettingsService($this->getContainer());

        $actualSettings = $settingsService->getLoggerSettings();

        $this->assertSame(10, $actualSettings->getLogFileDays());
        $this->assertFalse($actualSettings->isDebugMode());
    }

    public function testSettingsAreCachedPerSalesChannel(): void
    {
        $settingsService = new SettingsService($this->getContainer());

        $expectedSettings = $settingsService->getLoggerSettings();
        $actualSettings = $settingsService->getLoggerSettings();
        $differentSalesChannelSettings = $settingsService->getLoggerSettings(Uuid::randomHex());

        $this->assertSame($expectedSettings, $actualSettings);
        $this->assertNotSame($actualSettings, $differentSalesChannelSettings);
    }
}
