<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Settings;

use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class ApiSettingsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSettingsAreLoadedFromDatabase(): void
    {
        /**
         * @var SystemConfigService $systemConfigService
         */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $oldTestKey = $systemConfigService->get(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_TEST_API_KEY);
        $oldLiveKey = $systemConfigService->get(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_LIVE_API_KEY);

        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_TEST_MODE, true);
        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_LIVE_API_KEY, 'live_key');
        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_TEST_API_KEY, 'test_key');

        $devMode = (bool) EnvironmentHelper::getVariable('MOLLIE_DEV_MODE', false);
        $cypressMode = (bool) EnvironmentHelper::getVariable('MOLLIE_CYPRESS_MODE', false);
        $settingsService = new SettingsService($systemConfigService, $devMode, $cypressMode);
        $apiSettings = $settingsService->getApiSettings();

        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_LIVE_API_KEY, $oldLiveKey);
        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . ApiSettings::KEY_TEST_API_KEY, $oldTestKey);

        $this->assertSame('test_key', $apiSettings->getTestApiKey());
        $this->assertSame('live_key', $apiSettings->getLiveApiKey());
        $this->assertTrue($apiSettings->isTestMode());
        $this->assertSame('test_key', $apiSettings->getApiKey());
        $this->assertNotSame('live_key', $apiSettings->getApiKey());
    }

    public function testSettingsAreCachedPerSalesChannel(): void
    {
        $settingsService = $this->getContainer()->get(SettingsService::class);

        $expectedSettings = $settingsService->getApiSettings();
        $actualSettings = $settingsService->getApiSettings();
        $differentSalesChannelSettings = $settingsService->getApiSettings(Uuid::randomHex());

        $this->assertSame($expectedSettings, $actualSettings);
        $this->assertNotSame($actualSettings, $differentSalesChannelSettings);
    }
}
