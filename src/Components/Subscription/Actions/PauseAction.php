<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Exception;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Shopware\Core\Framework\Context;

class PauseAction extends BaseAction
{
    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function pauseSubscription(string $subscriptionId, Context $context): void
    {
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        $settings = $this->getPluginSettings($subscription->getSalesChannelId());

        # -------------------------------------------------------------------------------------

        if (!$settings->isSubscriptionsEnabled()) {
            throw new Exception('Subscription cannot be paused. Subscriptions are disabled for this Sales Channel');
        }

        if (!$settings->isSubscriptionsAllowPauseResume()) {
            throw new Exception('Subscriptions cannot be paused in this sales channel. Please adjust the plugin configuration.');
        }

        if (!$subscription->isPauseAllowed()) {
            throw new Exception('Pausing of the subscription is not possible because of its current status!');
        }

        if (!$this->isCancellationPeriodValid($subscription, $context)) {
            throw new Exception('Pausing of the subscription is not possible anymore. This can only be done before the notice period!');
        }

        # -------------------------------------------------------------------------------------

        $oldStatus = $subscription->getStatus();
        $newStatus = SubscriptionStatus::PAUSED;


        $gateway = $this->getMollieGateway($subscription);
        $gateway->cancelSubscription($subscription->getMollieId(), $subscription->getMollieCustomerId());

        $this->getRepository()->cancelSubscription($subscriptionId, $newStatus, $context);


        # -------------------------------------------------------------------------------------

        # fetch latest data again, just to be safe
        $subscription = $this->getRepository()->findById($subscriptionId, $context);


        $this->getStatusHistory()->markPaused($subscription, $oldStatus, $newStatus, $context);

        # FLOW BUILDER / BUSINESS EVENTS
        $event = $this->getFlowBuilderEventFactory()->buildSubscriptionPausedEvent($subscription->getCustomer(), $subscription, $context);
        $this->getFlowBuilderDispatcher()->dispatch($event);
    }
}
