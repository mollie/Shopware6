<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Actions\Base;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderDispatcherAdapterInterface;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionHistory\SubscriptionHistoryHandler;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

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
     * @throws \Exception
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

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    protected function getStatusHistory(): SubscriptionHistoryHandler
    {
        return $this->subscriptionHistory;
    }

    protected function getRepository(): SubscriptionRepository
    {
        return $this->repoSubscriptions;
    }

    protected function getPluginSettings(string $salesChannelId): MollieSettingStruct
    {
        return $this->pluginSettings->getSettings($salesChannelId);
    }

    protected function getPayloadBuilder(): MollieDataBuilder
    {
        return $this->mollieRequestBuilder;
    }

    protected function getSubscriptionBuilder(): SubscriptionBuilder
    {
        return $this->subscriptionBuilder;
    }

    protected function getCustomers(): CustomerService
    {
        return $this->customers;
    }

    protected function getMollieGateway(SubscriptionEntity $subscription): MollieGatewayInterface
    {
        $this->gwMollie->switchClient($subscription->getSalesChannelId());

        return $this->gwMollie;
    }

    protected function getFlowBuilderEventFactory(): FlowBuilderEventFactory
    {
        return $this->flowBuilderEventFactory;
    }

    protected function getFlowBuilderDispatcher(): FlowBuilderDispatcherAdapterInterface
    {
        return $this->flowBuilderDispatcher;
    }

    protected function isSubscriptionFeatureEnabled(OrderEntity $order): bool
    {
        return $this->getPluginSettings($order->getSalesChannelId())->isSubscriptionsEnabled();
    }

    /**
     * @throws \Exception
     */
    protected function isCancellationPeriodValid(SubscriptionEntity $subscription, Context $context): bool
    {
        $settings = $this->getPluginSettings($subscription->getSalesChannelId());

        $cancellationDays = $settings->getSubscriptionsCancellationDays();

        // now verify if we are in a valid range to cancel the subscription
        // depending on the plugin configuration it might only be possible
        // up until a few days before the renewal
        return $this->cancellationValidator->isCancellationAllowed($subscription->getNextPaymentAt(), $cancellationDays, new \DateTime());
    }

    protected function isMollieDevMode(): bool
    {
        return $this->pluginSettings->getEnvMollieDevMode();
    }
}
