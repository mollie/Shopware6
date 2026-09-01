<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Hook\BeforeSuite;
use Mollie\Shopware\Behat\Storage;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Integration\Data\SalesChannelTestBehaviour;
use PHPUnit\TextUI\Configuration\Builder;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Context as FrameworkContext;

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
