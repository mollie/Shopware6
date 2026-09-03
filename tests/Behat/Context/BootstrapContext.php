<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Component\Settings\Struct\CaptureSettings;
use Mollie\Shopware\Integration\Data\SalesChannelTestBehaviour;
use PHPUnit\TextUI\Configuration\Builder;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Context as FrameworkContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class BootstrapContext implements Context
{
    use SalesChannelTestBehaviour;

    #[BeforeSuite]
    public static function bootstrap(): void
    {
        require_once __DIR__ . '/../../bootstrap.php';

        (new Builder())->build(['phpunit']);
    }

    #[BeforeScenario]
    public function clearStorage(): void
    {
        // A scenario that needs a hold switches the direct payment off. That value outlives the
        // scenario, so without this reset the next one silently runs with the wrong capture mode.
        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->delete(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . CaptureSettings::KEY_DISABLED_METHODS);

        /** @var SettingsService $settingsService */
        $settingsService = $this->getContainer()->get(SettingsService::class);
        $settingsService->clearCache();

        Storage::clear();

        $this->clearCart();
    }

    private function clearCart(): void
    {
        $salesChannel = $this->findSalesChannelByDomain($_ENV['APP_URL'], FrameworkContext::createDefaultContext());
        $salesChannelContext = $this->getSalesChannelContext($salesChannel);

        /** @var CartService $cartService */
        $cartService = $this->getContainer()->get(CartService::class);
        $cartService->deleteCart($salesChannelContext);
    }
}
