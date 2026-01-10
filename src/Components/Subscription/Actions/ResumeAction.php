<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderFactory;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Repository\SubscriptionRepository;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\MollieDataBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Builder\SubscriptionBuilder;
use Kiener\MolliePayments\Components\Subscription\Services\Interval\IntervalCalculator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionHistory\SubscriptionHistoryHandler;
use Kiener\MolliePayments\Gateway\MollieGatewayInterface;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;

class ResumeAction extends BaseAction
{
    private IntervalCalculator $intervalCalculator;

    public function __construct(SettingsService $pluginSettings, SubscriptionRepository $repoSubscriptions, SubscriptionBuilder $subscriptionBuilder, MollieDataBuilder $mollieRequestBuilder, CustomerService $customers, MollieGatewayInterface $gwMollie, CancellationValidator $cancellationValidator, FlowBuilderFactory $flowBuilderFactory, FlowBuilderEventFactory $flowBuilderEventFactory, SubscriptionHistoryHandler $subscriptionHistory, LoggerInterface $logger)
    {
        parent::__construct($pluginSettings, $repoSubscriptions, $subscriptionBuilder, $mollieRequestBuilder, $customers, $gwMollie, $cancellationValidator, $flowBuilderFactory, $flowBuilderEventFactory, $subscriptionHistory, $logger);
        $this->intervalCalculator = new IntervalCalculator();
    }

    /**
     * @throws \Exception
     */
    public function resumeSubscription(string $subscriptionId, \DateTimeInterface $today, Context $context): void
    {
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        $settings = $this->getPluginSettings($subscription->getSalesChannelId());

        // -------------------------------------------------------------------------------------

        if (! $settings->isSubscriptionsEnabled()) {
            throw new \Exception('Subscription cannot be resumed. Subscriptions are disabled for this Sales Channel');
        }

        if (! $settings->isSubscriptionsAllowPauseResume()) {
            throw new \Exception('Subscriptions cannot be resumed in this sales channel. Please adjust the plugin configuration.');
        }

        if (! $subscription->isResumeAllowed()) {
            throw new \Exception('Resuming of the subscription is not possible because of its current status!');
        }
        $gateway = $this->getMollieGateway($subscription);
        // We assume that a subscription was cancelled long time ago, and the next possible payment date is in the past, so we set a new one from today
        $nextPaymentDate = $today;

        $latestSubscriptionHistory = $this->getLatestSubscriptionHistory($subscription);

        // doublecheck if there is a history for the subscription
        if ($latestSubscriptionHistory instanceof SubscriptionHistoryEntity) {
            $oldMollieSubscription = $gateway->getSubscription($latestSubscriptionHistory->getMollieId(), $subscription->getMollieCustomerId());
            /** @var \DateTimeInterface $oldStartDate */
            $oldStartDate = \DateTime::createFromFormat('Y-m-d', (string) $oldMollieSubscription->startDate);
            [$oldInterval, $oldIntervalUnit] = explode(' ', $oldMollieSubscription->interval);

            $nextInterval = $this->intervalCalculator->getNextIntervalDate($oldStartDate, (int) $oldInterval, $oldIntervalUnit);
            // we calculate the next possible payment date, based on the latest history entry
            /** @var \DateTimeInterface $nextPossiblePaymentDate */
            $nextPossiblePaymentDate = \DateTime::createFromFormat('Y-m-d', $nextInterval);
            // if the next possible payment date is in the future, we use this instead of today
            if ($nextPossiblePaymentDate > $today) {
                $nextPaymentDate = $nextPossiblePaymentDate;
            }
        }

        // -------------------------------------------------------------------------------------

        $oldStatus = $subscription->getStatus();
        $newStatus = SubscriptionStatus::RESUMED;

        $metaData = $subscription->getMetadata();

        $jsonPayload = $this->getPayloadBuilder()->buildRequestPayload(
            $subscription,
            $nextPaymentDate->format('Y-m-d'),
            (string) $metaData->getInterval(),
            (string) $metaData->getIntervalUnit()->value,
            (int) $metaData->getTimes(),
            $subscription->getMandateId()
        );

        $newMollieSubscription = $gateway->createSubscription($subscription->getMollieCustomerId(), $jsonPayload);

        // -------------------------------------------------------------------------------------

        $this->getRepository()->confirmNewSubscription(
            $subscription->getId(),
            (string) $newMollieSubscription->id,
            $newStatus,
            (string) $newMollieSubscription->customerId,
            $subscription->getMandateId(),
            (string) $newMollieSubscription->nextPaymentDate,
            $context
        );

        // -------------------------------------------------------------------------------------

        // fetch latest data again, just to be safe
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        // also add a history entry for this subscription
        $this->getStatusHistory()->markResumed($subscription, $oldStatus, $newStatus, $context);

        // FLOW BUILDER / BUSINESS EVENTS
        $event = $this->getFlowBuilderEventFactory()->buildSubscriptionResumedEvent($subscription->getCustomer(), $subscription, $context);
        $this->getFlowBuilderDispatcher()->dispatch($event);
    }

    private function getLatestSubscriptionHistory(SubscriptionEntity $subscription): ?SubscriptionHistoryEntity
    {
        $subscriptionHistories = $subscription->getHistoryEntries();

        $subscriptionHistories->sort(function (SubscriptionHistoryEntity $historyA, SubscriptionHistoryEntity $historyB) {
            return $historyB->getCreatedAt() <=> $historyA->getCreatedAt();
        });

        return $subscriptionHistories->first();
    }
}
