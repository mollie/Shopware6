<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Exception;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Shopware\Core\Framework\Context;

class CancelAction extends BaseAction
{
    /**
     * @param string $subscriptionId
     * @param Context $context
     * @throws Exception
     * @return void
     */
    public function cancelSubscription(string $subscriptionId, Context $context): void
    {
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        # -------------------------------------------------------------------------------------

        if (!$subscription->isCancellationAllowed()) {
            throw new Exception('Cancellation of the subscription is not possible because of its current status!');
        }

        $allowCancellation = $this->isCancellationPeriodValid($subscription, $context);

        if (!$allowCancellation) {
            throw new Exception('Cancellation of the subscription is not possible anymore. This can only be done before the notice period!');
        }

        # -------------------------------------------------------------------------------------

        $gateway = $this->getMollieGateway($subscription);

        $gateway->cancelSubscription($subscription->getMollieId(), $subscription->getMollieCustomerId());

        $oldStatus = $subscription->getStatus();
        $newStatus = SubscriptionStatus::CANCELED;

        $this->getRepository()->cancelSubscription($subscriptionId, $newStatus, $context);


        # -------------------------------------------------------------------------------------

        # fetch latest data again, just to be safe
        $subscription = $this->getRepository()->findById($subscriptionId, $context);

        $this->getStatusHistory()->markCanceled($subscription, $oldStatus, $newStatus, $context);

        # FLOW BUILDER / BUSINESS EVENTS
        $event = $this->getFlowBuilderEventFactory()->buildSubscriptionCancelledEvent($subscription->getCustomer(), $subscription, $context);
        $this->getFlowBuilderDispatcher()->dispatch($event);
    }

    /**
     * Check the subscription is cancelable or not
     *
     * @param SubscriptionEntity $subscription
     * @param Context $context
     * @throws Exception
     * @return bool
     */
    public function isCancelable(SubscriptionEntity $subscription, Context $context): bool
    {
        if (!$subscription->isCancellationAllowed()) {
            return false;
        }

        $allowCancellation = $this->isCancellationPeriodValid($subscription, $context);
        if (!$allowCancellation) {
            return false;
        }

        return true;
    }
}
