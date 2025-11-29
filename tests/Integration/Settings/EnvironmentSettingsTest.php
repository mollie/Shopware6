<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Settings;

use Mollie\Shopware\Component\Settings\SettingsService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

final class EnvironmentSettingsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testSettingsAreLoadedFromEnvironment(): void
    {
        $_SERVER['MOLLIE_CYPRESS_MODE'] = true;
        $_SERVER['MOLLIE_DEV_MODE'] = false;

        $devMode = (bool) EnvironmentHelper::getVariable('MOLLIE_DEV_MODE', false);
        $cypressMode = (bool) EnvironmentHelper::getVariable('MOLLIE_CYPRESS_MODE', false);
        $settingsService = new SettingsService($this->getContainer(), $devMode, $cypressMode);

        $environmentSettings = $settingsService->getEnvironmentSettings();

        $this->assertFalse($environmentSettings->isDevMode());
        $this->assertTrue($environmentSettings->isCypressMode());
    }
}
