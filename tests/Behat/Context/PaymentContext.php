<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class PaymentContext extends ShopwareContext
{
    use PaymentMethodTestBehaviour;

    /**
     * @var array<string, mixed> the value each configuration key had before a scenario changed it
     */
    private array $previousConfigValues = [];

    #[Given('payment method :arg1 exists and active')]
    public function paymentMethodExistsAndActive(string $paymentMethodIdentifier): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $paymentMethod = $this->getPaymentMethodByTechnicalName($paymentMethodIdentifier, $salesChannelContext->getContext());
        $this->activatePaymentMethod($paymentMethod, $salesChannelContext->getContext());
        $this->assignPaymentMethodToSalesChannel($paymentMethod, $salesChannelContext->getSalesChannel(), $salesChannelContext->getContext());
    }

    #[Given('plugin configuration :arg1 is set to :arg2')]
    public function pluginConfigurationIsSetTo(string $configKey, string $configValue): void
    {
        /**
         * @var SystemConfigService $systemConfigService
         */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        if ($configValue === 'true') {
            $configValue = true;
        }
        if ($configValue === 'false') {
            $configValue = false;
        }
        if (is_numeric($configValue)) {
            $configValue = (float) $configValue;
        }
        if (is_int($configValue)) {
            $configValue = (int) $configValue;
        }

        $fullConfigKey = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . $configKey;
        if (! array_key_exists($fullConfigKey, $this->previousConfigValues)) {
            $this->previousConfigValues[$fullConfigKey] = $systemConfigService->get($fullConfigKey);
        }

        $systemConfigService->set($fullConfigKey, $configValue);

        $this->getContainer()->get(SettingsService::class)->clearCache();
    }

    /**
     * A configuration value is global and outlives the scenario that set it, so a scenario which
     * switches one off would silently run every following one with that switch. BootstrapContext
     * resets the direct payment keys only.
     */
    #[AfterScenario]
    public function restorePluginConfiguration(): void
    {
        if (count($this->previousConfigValues) === 0) {
            return;
        }

        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        foreach ($this->previousConfigValues as $configKey => $configValue) {
            $systemConfigService->set($configKey, $configValue);
        }

        $this->previousConfigValues = [];

        $this->getContainer()->get(SettingsService::class)->clearCache();
    }
}
