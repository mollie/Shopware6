<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat;

use Behat\Step\Given;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class PaymentContext extends ShopwareContext
{
    use PaymentMethodTestBehaviour;

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

        $systemConfigService->set(SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . $configKey, $configValue);
    }
}
