<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Exception;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Shopware\Core\Framework\Context;

class ResumeAction extends BaseAction
{
    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function resumeSubscription(string $subscriptionId, Context $context): void
    {
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        $settings = $this->getPluginSettings($subscription->getSalesChannelId());

        # -------------------------------------------------------------------------------------

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription cannot be resumed. Subscriptions are disabled for this Sales Channel');
        }

        if (!$settings->isSubscriptionsAllowPauseResume()) {
            throw new Exception('Subscriptions cannot be resumed in this sales channel. Please adjust the plugin configuration.');
        }

        if (!$subscription->isResumeAllowed()) {
            throw new Exception('Resuming of the subscription is not possible because of its current status!');
        }

        # -------------------------------------------------------------------------------------

        $oldStatus = $subscription->getStatus();
        $newStatus = SubscriptionStatus::RESUMED;

        $metaData = $subscription->getMetadata();

        # TODO, what if we are in the currently (cancelled) period
        $jsonPayload = $this->getPayloadBuilder()->buildRequestPayload(
            $subscription,
            $metaData->getStartDate(),
            (string)$metaData->getInterval(),
            $metaData->getIntervalUnit(),
            (int)$metaData->getTimes(),
            $subscription->getMandateId()
        );

        $gateway = $this->getMollieGateway($subscription);

        $newMollieSubscription = $gateway->createSubscription($subscription->getMollieCustomerId(), $jsonPayload);

        # -------------------------------------------------------------------------------------

        $this->getRepository()->confirmNewSubscription(
            $subscription->getId(),
            (string)$newMollieSubscription->id,
            $newStatus,
            (string)$newMollieSubscription->customerId,
            $subscription->getMandateId(),
            (string)$newMollieSubscription->nextPaymentDate,
            $context
        );

        # -------------------------------------------------------------------------------------

        # fetch latest data again, just to be safe
        $subscription = $this->getRepository()->findById($subscriptionId, $context);


        # also add a history entry for this subscription
        $this->getStatusHistory()->markResumed($subscription, $oldStatus, $newStatus, $context);

        # FLOW BUILDER / BUSINESS EVENTS
        $event = $this->getFlowBuilderEventFactory()->buildSubscriptionResumedEvent($subscription->getCustomer(), $subscription, $context);
        $this->getFlowBuilderDispatcher()->dispatch($event);
    }
}
