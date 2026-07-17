<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Settings;

use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Integration\Data\ShopwareTestBehaviour;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

#[CoversClass(SettingsService::class)]
#[Group('core')]
final class EnvironmentSettingsTest extends TestCase
{
    use ShopwareTestBehaviour;
    use IntegrationTestBehaviour;

    public function testSettingsAreLoadedFromEnvironment(): void
    {
        $_SERVER['MOLLIE_CYPRESS_MODE'] = 1;
        $_SERVER['MOLLIE_DEV_MODE'] = 0;
        /**
         * @var SystemConfigService $systemConfigService
         */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $devMode = (string) EnvironmentHelper::getVariable('MOLLIE_DEV_MODE', '0');
        $cypressMode = (string) EnvironmentHelper::getVariable('MOLLIE_CYPRESS_MODE', '0');
        $settingsService = new SettingsService($systemConfigService, $devMode, $cypressMode);

        $environmentSettings = $settingsService->getEnvironmentSettings();

        $this->assertFalse($environmentSettings->isDevMode());
        $this->assertTrue($environmentSettings->isCypressMode());
    }
}
