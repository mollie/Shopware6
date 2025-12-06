<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Settings;

use Mollie\Shopware\Component\Settings\SettingsService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(SettingsService::class)]
final class EnvironmentSettingsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSettingsAreLoadedFromEnvironment(): void
    {
        $_SERVER['MOLLIE_CYPRESS_MODE'] = true;
        $_SERVER['MOLLIE_DEV_MODE'] = false;
        /**
         * @var SystemConfigService $systemConfigService
         */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $devMode = (bool) EnvironmentHelper::getVariable('MOLLIE_DEV_MODE', false);
        $cypressMode = (bool) EnvironmentHelper::getVariable('MOLLIE_CYPRESS_MODE', false);
        $settingsService = new SettingsService($systemConfigService, $devMode, $cypressMode);

        $environmentSettings = $settingsService->getEnvironmentSettings();

        $this->assertFalse($environmentSettings->isDevMode());
        $this->assertTrue($environmentSettings->isCypressMode());
    }
}
