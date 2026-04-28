<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Settings\SettingsService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

final class BootstrapContext implements Context
{
    use IntegrationTestBehaviour;

    #[BeforeSuite]
    public static function bootstrap(): void
    {
        require_once __DIR__ . '/../../bootstrap.php';
    }

    #[BeforeScenario]
    public function clearStorage(): void
    {
        /** @var SettingsService $settingsService */
        $settingsService = $this->getContainer()->get(SettingsService::class);
        $settingsService->clearCache();

        Storage::clear();
    }
}
