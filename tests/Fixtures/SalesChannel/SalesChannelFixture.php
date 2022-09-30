<?php

namespace MolliePayments\Fixtures\SalesChannel;


use Basecom\FixturePlugin\Fixture;
use Basecom\FixturePlugin\FixtureBag;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;


class SalesChannelFixture extends Fixture
{

    private const  CONFIG_PREFIX = 'MolliePayments.config.';

    /**
     * @var EntityRepositoryInterface
     */
    private $repoSalesChannels;

    /**
     * @var EntityRepositoryInterface
     */
    private $repoPaymentMethods;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var array<mixed>
     */
    private $defaultConfigs;


    /**
     * @param EntityRepositoryInterface $repoSalesChannels
     * @param EntityRepositoryInterface $repoPaymentMethods
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(EntityRepositoryInterface $repoSalesChannels, EntityRepositoryInterface $repoPaymentMethods, SystemConfigService $systemConfigService)
    {
        $this->repoSalesChannels = $repoSalesChannels;
        $this->repoPaymentMethods = $repoPaymentMethods;
        $this->systemConfigService = $systemConfigService;


        $this->defaultConfigs['testMode'] = true;
        // ------------------------------------------------------------------
        $this->defaultConfigs['enableCreditCardComponents'] = true;
        $this->defaultConfigs['shopwareFailedPayment'] = true;
        $this->defaultConfigs['enableApplePayDirect'] = true;
        $this->defaultConfigs['createCustomersAtMollie'] = false;
        $this->defaultConfigs['useMolliePaymentMethodLimits'] = false;
        // ------------------------------------------------------------------
        $this->defaultConfigs['refundManagerEnabled'] = true;
        $this->defaultConfigs['refundManagerAutoStockReset'] = true;
        $this->defaultConfigs['refundManagerVerifyRefund'] = true;
        $this->defaultConfigs['refundManagerShowInstructions'] = true;
        // ------------------------------------------------------------------
        $this->defaultConfigs['debugMode'] = true;
        $this->defaultConfigs['automaticShipping'] = false;
        $this->defaultConfigs['paymentMethodBankTransferDueDateDays'] = 2;
        $this->defaultConfigs['orderLifetimeDays'] = 4;
        // ------------------------------------------------------------------
        $this->defaultConfigs['orderStateWithAAuthorizedTransaction'] = 'in_progress';
        $this->defaultConfigs['orderStateWithAPaidTransaction'] = 'completed';
        $this->defaultConfigs['orderStateWithAFailedTransaction'] = 'open';
        $this->defaultConfigs['orderStateWithACancelledTransaction'] = 'cancelled';
        // ------------------------------------------------------------------
        $this->defaultConfigs['subscriptionsEnabled'] = true;
        $this->defaultConfigs['subscriptionsShowIndicator'] = true;
        $this->defaultConfigs['subscriptionsAllowAddressEditing'] = true;
        $this->defaultConfigs['subscriptionSkipRenewalsOnFailedPayments'] = false;
        $this->defaultConfigs['subscriptionsReminderDays'] = 2;
        $this->defaultConfigs['subscriptionsCancellationDays'] = 0;
    }


    /**
     * @return string[]
     */
    public function groups(): array
    {
        return [
            'mollie',
        ];
    }

    /**
     * @param FixtureBag $bag
     * @return void
     */
    public function load(FixtureBag $bag): void
    {
        $ctx = Context::createDefaultContext();

        # first delete all existing configurations
        # of the specific sales channels
        $salesChannelIds = $this->repoSalesChannels->searchIds(new Criteria([]), $ctx)->getIds();

        $this->setDefaultConfig($salesChannelIds);

        $this->assignPaymentMethods($salesChannelIds, $ctx);
    }

    /**
     * @param array $salesChannelIds
     * @return void
     */
    private function setDefaultConfig(array $salesChannelIds): void
    {
        foreach ($salesChannelIds as $id) {
            $this->deleteConfig($id);
        }

        # now just add 1 inherited configuration
        $this->setConfig(null);
    }

    /**
     * @param array<mixed> $salesChannelIds
     * @param Context $ctx
     * @return void
     */
    private function assignPaymentMethods(array $salesChannelIds, Context $ctx): void
    {
        $paymentUpdates = [];
        $molliePaymentMethodIdsPrepared = [];

        $molliePaymentMethodIds = $this->repoPaymentMethods->searchIds(new Criteria([]), $ctx)->getIds();


        foreach ($molliePaymentMethodIds as $id) {
            $molliePaymentMethodIdsPrepared[] = [
                'id' => $id,
            ];
        }

        foreach ($salesChannelIds as $id) {

            $paymentUpdates[] = [
                'id' => $id,
                'paymentMethods' => $molliePaymentMethodIdsPrepared,
            ];
        }

        $this->repoSalesChannels->update($paymentUpdates, $ctx);
    }

    /**
     * @param string $salesChannelId
     * @return void
     */
    private function deleteConfig(string $salesChannelId): void
    {
        foreach ($this->defaultConfigs as $key) {
            $this->systemConfigService->delete(self::CONFIG_PREFIX . $key, $salesChannelId);
        }
    }

    /**
     * @param string|null $salesChannelId
     * @return void
     */
    private function setConfig(?string $salesChannelId): void
    {
        foreach ($this->defaultConfigs as $key => $value) {
            $this->systemConfigService->set(self::CONFIG_PREFIX . $key, $value, $salesChannelId);
        }
    }

}
