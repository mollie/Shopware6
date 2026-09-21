<?php
declare(strict_types=1);

namespace Mollie\Shopware\Behat\Context;

use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Mollie\Shopware\Component\Settings\SettingsService;
use Mollie\Shopware\Integration\Data\PaymentMethodTestBehaviour;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class PaymentContext extends ShopwareContext
{
    use PaymentMethodTestBehaviour;

    /**
     * @var array<string, mixed> the value each configuration key had before a scenario changed it
     */
    private array $previousConfigValues = [];

    private ?string $configuredSalesChannelId = null;

    /**
     * @var array<string, ?string>
     */
    private array $previousAvailabilityRules = [];

    #[Given('payment method :arg1 exists and active')]
    public function paymentMethodExistsAndActive(string $paymentMethodIdentifier): void
    {
        $salesChannelContext = $this->getCurrentSalesChannelContext();
        $paymentMethod = $this->getPaymentMethodByTechnicalName($paymentMethodIdentifier, $salesChannelContext->getContext());
        $this->activatePaymentMethod($paymentMethod, $salesChannelContext->getContext());
        $this->assignPaymentMethodToSalesChannel($paymentMethod, $salesChannelContext->getSalesChannel(), $salesChannelContext->getContext());
    }

    #[Given('payment method :arg1 has availability rule :arg2')]
    public function paymentMethodHasAvailabilityRule(string $paymentMethodIdentifier, string $ruleName): void
    {
        $context = $this->getCurrentSalesChannelContext()->getContext();
        $paymentMethod = $this->getPaymentMethodByTechnicalName($paymentMethodIdentifier, $context);

        /** @var EntityRepository $ruleRepository */
        $ruleRepository = $this->getContainer()->get('rule.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $ruleName));
        $rule = $ruleRepository->search($criteria, $context)->first();
        if (! $rule instanceof RuleEntity) {
            throw new \RuntimeException(sprintf('Rule "%s" not found, load the mollie fixtures first', $ruleName));
        }

        if (! array_key_exists($paymentMethod->getId(), $this->previousAvailabilityRules)) {
            $this->previousAvailabilityRules[$paymentMethod->getId()] = $paymentMethod->getAvailabilityRuleId();
        }

        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $paymentMethodRepository->upsert([[
            'id' => $paymentMethod->getId(),
            'availabilityRuleId' => $rule->getId(),
        ]], $context);
    }

    #[AfterScenario]
    public function restoreAvailabilityRules(): void
    {
        if (count($this->previousAvailabilityRules) === 0) {
            return;
        }

        $context = $this->getCurrentSalesChannelContext()->getContext();

        /** @var EntityRepository $paymentMethodRepository */
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');

        $updates = [];
        foreach ($this->previousAvailabilityRules as $paymentMethodId => $availabilityRuleId) {
            $updates[] = [
                'id' => $paymentMethodId,
                'availabilityRuleId' => $availabilityRuleId,
            ];
        }
        $paymentMethodRepository->upsert($updates, $context);

        $this->previousAvailabilityRules = [];
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

        $salesChannelId = $this->getCurrentSalesChannelContext()->getSalesChannelId();
        $this->configuredSalesChannelId = $salesChannelId;

        $fullConfigKey = SettingsService::SYSTEM_CONFIG_DOMAIN . '.' . $configKey;
        if (! array_key_exists($fullConfigKey, $this->previousConfigValues)) {
            $this->previousConfigValues[$fullConfigKey] = $systemConfigService->get($fullConfigKey, $salesChannelId);
        }

        $systemConfigService->set($fullConfigKey, $configValue, $salesChannelId);

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
            $systemConfigService->set($configKey, $configValue, $this->configuredSalesChannelId);
        }

        $this->previousConfigValues = [];
        $this->configuredSalesChannelId = null;

        $this->getContainer()->get(SettingsService::class)->clearCache();
    }
}
