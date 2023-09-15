<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use DateTime;
use Exception;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionHistory\SubscriptionHistoryHandler;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionReminder\ReminderValidator;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Repository\SalesChannel\SalesChannelRepositoryInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class RemindAction extends BaseAction
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $repoSalesChannel;

    /**
     * @var ReminderValidator
     */
    private $reminderValidator;


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
     * @param SalesChannelRepositoryInterface $repoSalesChannel
     * @param ReminderValidator $reminderValidator
     * @throws Exception
     */
    public function __construct(SettingsService $pluginSettings, SubscriptionRepository $repoSubscriptions, SubscriptionBuilder $subscriptionBuilder, MollieDataBuilder $mollieRequestBuilder, CustomerService $customers, MollieGatewayInterface $gwMollie, CancellationValidator $cancellationValidator, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, SubscriptionHistoryHandler $subscriptionHistory, LoggerInterface $logger, SalesChannelRepositoryInterface $repoSalesChannel, ReminderValidator $reminderValidator)
    {
        parent::__construct(
            $pluginSettings,
            $repoSubscriptions,
            $subscriptionBuilder,
            $mollieRequestBuilder,
            $customers,
            $gwMollie,
            $cancellationValidator,
            $flowBuilderFactory,
            $flowBuilderEventFactory,
            $subscriptionHistory,
            $logger
        );

        $this->repoSalesChannel = $repoSalesChannel;
        $this->reminderValidator = $reminderValidator;
    }

    /**
     * @param Context $context
     * @throws Exception
     * @return int
     */
    public function remindSubscriptionRenewal(Context $context): int
    {
        $remindedCount = 0;

        $today = new DateTime();

        $salesChannels = $this->repoSalesChannel->search(new Criteria(), $context);


        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $settings = $this->getPluginSettings($salesChannel->getId());

            if (!$settings->isSubscriptionsEnabled()) {
                continue;
            }

            $daysOffset = $settings->getSubscriptionsReminderDays();

            $availableSubscriptions = $this->getRepository()->findByReminderRangeReached($salesChannel->getId(), $context);

            /** @var SubscriptionEntity $subscription */
            foreach ($availableSubscriptions->getElements() as $subscription) {
                # if it's not active in Mollie, then don't do anything
                if ($subscription->getStatus() !== SubscriptionStatus::ACTIVE && $subscription->getStatus() !== SubscriptionStatus::RESUMED) {
                    continue;
                }

                # now check if we are allowed to remind or if it was already done
                $shouldRemind = $this->reminderValidator->shouldRemind(
                    $subscription->getNextPaymentAt(),
                    $today,
                    $daysOffset,
                    $subscription->getLastRemindedAt()
                );

                if (!$shouldRemind) {
                    continue;
                }

                $customer = $this->getCustomers()->getCustomer($subscription->getCustomerId(), $context);

                if (!$customer instanceof CustomerEntity) {
                    throw new Exception('Shopware Customer not found for Subscription! Cannot remind anyone!');
                }

                # --------------------------------------------------------------------------------------------------
                # FLOW BUILDER / BUSINESS EVENTS

                $event = $this->getFlowBuilderEventFactory()->buildSubscriptionRemindedEvent($customer, $subscription, $salesChannel, $context);
                $this->getFlowBuilderDispatcher()->dispatch($event);

                # --------------------------------------------------------------------------------------------------

                $this->getRepository()->markReminded($subscription->getId(), $context);

                $this->getStatusHistory()->markReminded($subscription, $context);


                $remindedCount++;
            }
        }

        return $remindedCount;
    }
}
