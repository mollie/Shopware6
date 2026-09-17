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
        // A scenario that switches a direct payment on or off writes the global configuration value
        // and outlives itself, so without this reset the next one silently runs with whatever the
        // last one wanted. A deleted key means "no choice stored", which leaves exactly the capture
        // mode the marker interfaces of the handler imply - the same behaviour as the default value
        // in config.xml. Every scenario that cares therefore names its switch.
        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $salesChannelId = $this->findSalesChannelByDomain($_ENV['APP_URL'], FrameworkContext::createDefaultContext())->getId();

        $directPaymentPrefix = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . CaptureSettings::KEY_PREFIX_DIRECT_PAYMENT;
        $configKeys = array_merge(
            array_keys($systemConfigService->getDomain(SettingsService::SYSTEM_CONFIG_DOMAIN)),
            array_keys($systemConfigService->getDomain(SettingsService::SYSTEM_CONFIG_DOMAIN, $salesChannelId, false))
        );

        foreach (array_unique($configKeys) as $configKey) {
            if (! str_starts_with((string) $configKey, $directPaymentPrefix)) {
                continue;
            }

            $systemConfigService->delete((string) $configKey);
            $systemConfigService->delete((string) $configKey, $salesChannelId);
        }

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
