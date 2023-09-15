<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Exception;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Kiener\MolliePayments\Components\Subscription\Services\Interval\IntervalCalculator;
use Shopware\Core\Framework\Context;

class SkipAction extends BaseAction
{
    /**
     * @param string $subscriptionId
     * @param int $skipCount
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function skipSubscription(string $subscriptionId, int $skipCount, Context $context): void
    {
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        $settings = $this->getPluginSettings($subscription->getSalesChannelId());

        # -------------------------------------------------------------------------------------

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription cannot be skipped. Subscriptions are disabled for this Sales Channel');
        }

        if (!$settings->isSubscriptionsAllowPauseResume()) {
            throw new Exception('Subscriptions cannot be skipped in this sales channel. Please adjust the plugin configuration.');
        }

        if (!$subscription->isSkipAllowed()) {
            throw new Exception('Skipping of the subscription is not possible because of its current status!');
        }

        # now verify if we are in a valid range to cancel the subscription
        # depending on the plugin configuration it might only be possible
        # up until a few days before the renewal
        $allowPausing = $this->isCancellationPeriodValid($subscription, $context);

        if (!$allowPausing) {
            throw new Exception('Skipping of the subscription is not possible anymore.This can only be done before the notice period!');
        }

        $currentSubscriptionNextPaymentAt = $subscription->getNextPaymentAt();

        if ($currentSubscriptionNextPaymentAt === null) {
            throw new Exception('Cannot skip subscription ' . $subscription->getId() . '. We dont know when the next payment is, and therefore we cannot create a new subscription after this date');
        }

        # -------------------------------------------------------------------------------------

        $oldStatus = $subscription->getStatus();
        $newStatus = SubscriptionStatus::SKIPPED;

        # -------------------------------------------------------------------------------------
        # cancel our current mollie subscription
        # as well as our shopware subscription

        $gateway = $this->getMollieGateway($subscription);
        $gateway->cancelSubscription($subscription->getMollieId(), $subscription->getMollieCustomerId());

        $this->getRepository()->skipSubscription($subscriptionId, $newStatus, $context);

        # -------------------------------------------------------------------------------------
        # now create a new Mollie subscription
        # that starts in the future after our regular interval

        $metaData = $subscription->getMetadata();
        $intervalCalculator = new IntervalCalculator();


        $nextPaymentDate = $intervalCalculator->getNextIntervalDate(
            $currentSubscriptionNextPaymentAt,
            (int)$metaData->getInterval(),
            $metaData->getIntervalUnit()
        );

        $jsonPayload = $this->getPayloadBuilder()->buildRequestPayload(
            $subscription,
            $nextPaymentDate,
            (string)$metaData->getInterval(),
            $metaData->getIntervalUnit(),
            (int)$metaData->getTimes(),
            $subscription->getMandateId()
        );

        $newMollieSubscription = $gateway->createSubscription($subscription->getMollieCustomerId(), $jsonPayload);

        $this->getRepository()->confirmNewSubscription(
            $subscription->getId(),
            (string)$newMollieSubscription->id,
            $newStatus,
            (string)$newMollieSubscription->customerId,
            $subscription->getMandateId(),
            $nextPaymentDate,
            $context
        );

        # -------------------------------------------------------------------------------------

        # fetch latest data again, just to be safe
        $subscription = $this->getRepository()->findById($subscriptionId, $context);


        # also add a history entry for this subscription
        $this->getStatusHistory()->markSkipped($subscription, $oldStatus, $newStatus, $context);


        # FLOW BUILDER / BUSINESS EVENTS
        $event = $this->getFlowBuilderEventFactory()->buildSubscriptionSkippedEvent($subscription->getCustomer(), $subscription, $context);
        $this->getFlowBuilderDispatcher()->dispatch($event);
    }
}
