<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Actions;

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
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class RemindAction extends BaseAction
{
    /**
     * @var LoggerInterface
     */
    protected $log;
    /**
     * @var EntityRepository<EntityCollection<SalesChannelEntity>>
     */
    private $repoSalesChannel;

    /**
     * @var ReminderValidator
     */
    private $reminderValidator;

    /**
     * @param EntityRepository<EntityCollection<SalesChannelEntity>> $repoSalesChannel
     *
     * @throws \Exception
     */
    public function __construct(SettingsService $pluginSettings, SubscriptionRepository $repoSubscriptions, SubscriptionBuilder $subscriptionBuilder, MollieDataBuilder $mollieRequestBuilder, CustomerService $customers, MollieGatewayInterface $gwMollie, CancellationValidator $cancellationValidator, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, SubscriptionHistoryHandler $subscriptionHistory, LoggerInterface $logger, $repoSalesChannel, ReminderValidator $reminderValidator)
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
     * @throws \Exception
     */
    public function remindSubscriptionRenewal(Context $context): int
    {
        $remindedCount = 0;

        $today = new \DateTime();

        $salesChannels = $this->repoSalesChannel->search(new Criteria(), $context);

        /** @var SalesChannelEntity $salesChannel */
        foreach ($salesChannels as $salesChannel) {
            $settings = $this->getPluginSettings($salesChannel->getId());

            if (! $settings->isSubscriptionsEnabled()) {
                continue;
            }

            $message = 'Starting Subscription Renewal Reminder from Scheduled Tasks for SalesChannel "%s".';
            $message = sprintf($message, $salesChannel->getName());

            $this->getLogger()->info($message);

            $daysOffset = $settings->getSubscriptionsReminderDays();

            $availableSubscriptions = $this->getRepository()->findByReminderRangeReached($salesChannel->getId(), $context);

            /** @var SubscriptionEntity $subscription */
            foreach ($availableSubscriptions->getElements() as $subscription) {
                // if it's not active in Mollie, then don't do anything
                if ($subscription->getStatus() !== SubscriptionStatus::ACTIVE && $subscription->getStatus() !== SubscriptionStatus::RESUMED) {
                    continue;
                }

                // now check if we are allowed to remind or if it was already done
                $shouldRemind = $this->reminderValidator->shouldRemind(
                    $subscription->getNextPaymentAt(),
                    $today,
                    $daysOffset,
                    $subscription->getLastRemindedAt()
                );

                if (! $shouldRemind) {
                    continue;
                }

                $customer = $this->getCustomers()->getCustomer($subscription->getCustomerId(), $context);

                if (! $customer instanceof CustomerEntity) {
                    throw new \Exception('Shopware Customer not found for Subscription! Cannot remind anyone!');
                }

                // --------------------------------------------------------------------------------------------------
                // FLOW BUILDER / BUSINESS EVENTS

                $event = $this->getFlowBuilderEventFactory()->buildSubscriptionRemindedEvent($customer, $subscription, $salesChannel, $context);
                $this->getFlowBuilderDispatcher()->dispatch($event);

                // --------------------------------------------------------------------------------------------------

                $this->getRepository()->markReminded($subscription->getId(), $context);

                $this->getStatusHistory()->markReminded($subscription, $context);

                ++$remindedCount;
            }
        }

        return $remindedCount;
    }
}
