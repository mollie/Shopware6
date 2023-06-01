<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions\Base;

use DateTime;
use Exception;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionHistory\SubscriptionHistoryHandler;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BaseAction
{
    /**
     * @var SettingsService
     */
    private $pluginSettings;

    /**
     * @var SubscriptionRepository
     */
    private $repoSubscriptions;

    /**
     * @var SubscriptionBuilder
     */
    private $subscriptionBuilder;

    /**
     * @var MollieDataBuilder
     */
    private $mollieRequestBuilder;

    /**
     * @var CustomerService
     */
    private $customers;

    /**
     * @var MollieGatewayInterface
     */
    private $gwMollie;

    /**
     * @var CancellationValidator
     */
    private $cancellationValidator;

    /**
     * @var FlowBuilderDispatcherAdapterInterface
     */
    private $flowBuilderDispatcher;

    /**
     * @var FlowBuilderEventFactory
     */
    private $flowBuilderEventFactory;

    /**
     * @var SubscriptionHistoryHandler
     */
    private $subscriptionHistory;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param SettingsService $pluginSettings
     * @param SubscriptionRepository $repoSubscriptions
     * @param SubscriptionBuilder $subscriptionBuilder
     * @param MollieDataBuilder $mollieRequestBuilder
     * @param CustomerService $customers
     * @param MollieGatewayInterface $gwMollie
     * @param CancellationValidator $cancellationValidator
     * @param FlowBuilderFactory $flowBuilderFactory
     * @param FlowBuilderEventFactory $flowBuilderEventFactory
     * @param SubscriptionHistoryHandler $subscriptionHistory
     * @param LoggerInterface $logger
     * @throws Exception
     */
    public function __construct(SettingsService $pluginSettings, SubscriptionRepository $repoSubscriptions, SubscriptionBuilder $subscriptionBuilder, MollieDataBuilder $mollieRequestBuilder, CustomerService $customers, MollieGatewayInterface $gwMollie, CancellationValidator $cancellationValidator, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, SubscriptionHistoryHandler $subscriptionHistory, LoggerInterface $logger)
    {
        $this->pluginSettings = $pluginSettings;
        $this->repoSubscriptions = $repoSubscriptions;
        $this->subscriptionBuilder = $subscriptionBuilder;
        $this->mollieRequestBuilder = $mollieRequestBuilder;
        $this->customers = $customers;
        $this->gwMollie = $gwMollie;
        $this->cancellationValidator = $cancellationValidator;
        $this->flowBuilderEventFactory = $flowBuilderEventFactory;
        $this->subscriptionHistory = $subscriptionHistory;
        $this->logger = $logger;

        $this->flowBuilderDispatcher = $flowBuilderFactory->createDispatcher();
    }


    /**
     * @return LoggerInterface
     */
    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return SubscriptionHistoryHandler
     */
    protected function getStatusHistory(): SubscriptionHistoryHandler
    {
        return $this->subscriptionHistory;
    }

    /**
     * @return SubscriptionRepository
     */
    protected function getRepository(): SubscriptionRepository
    {
        return $this->repoSubscriptions;
    }

    /**
     * @param string $salesChannelId
     * @return MollieSettingStruct
     */
    protected function getPluginSettings(string $salesChannelId): MollieSettingStruct
    {
        return $this->pluginSettings->getSettings($salesChannelId);
    }

    /**
     * @return MollieDataBuilder
     */
    protected function getPayloadBuilder(): MollieDataBuilder
    {
        return $this->mollieRequestBuilder;
    }

    /**
     * @return SubscriptionBuilder
     */
    protected function getSubscriptionBuilder(): SubscriptionBuilder
    {
        return $this->subscriptionBuilder;
    }

    /**
     * @return CustomerService
     */
    protected function getCustomers(): CustomerService
    {
        return $this->customers;
    }

    /**
     * @param SubscriptionEntity $subscription
     * @return MollieGatewayInterface
     */
    protected function getMollieGateway(SubscriptionEntity $subscription): MollieGatewayInterface
    {
        $this->gwMollie->switchClient($subscription->getSalesChannelId());

        return $this->gwMollie;
    }

    /**
     * @return FlowBuilderEventFactory
     */
    protected function getFlowBuilderEventFactory(): FlowBuilderEventFactory
    {
        return $this->flowBuilderEventFactory;
    }

    /**
     * @return FlowBuilderDispatcherAdapterInterface
     */
    protected function getFlowBuilderDispatcher(): FlowBuilderDispatcherAdapterInterface
    {
        return $this->flowBuilderDispatcher;
    }


    /**
     * @param OrderEntity $order
     * @return bool
     */
    protected function isSubscriptionFeatureEnabled(OrderEntity $order): bool
    {
        return $this->getPluginSettings($order->getSalesChannelId())->isSubscriptionsEnabled();
    }

    /**
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @throws Exception
     * @return bool
     */
    protected function isCancellationPeriodValid(SubscriptionEntity $subscription, Context $context): bool
    {
        $settings = $this->getPluginSettings($subscription->getSalesChannelId());

        $cancellationDays = $settings->getSubscriptionsCancellationDays();

        # now verify if we are in a valid range to cancel the subscription
        # depending on the plugin configuration it might only be possible
        # up until a few days before the renewal
        return $this->cancellationValidator->isCancellationAllowed($subscription->getNextPaymentAt(), $cancellationDays, new DateTime());
    }

    /**
     * @return bool
     */
    protected function isMollieDevMode(): bool
    {
        return $this->pluginSettings->getEnvMollieDevMode();
    }
}
