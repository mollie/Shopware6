<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Kiener\MolliePayments\Exception\CustomerCouldNotBeFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class ConfirmAction extends BaseAction
{
    /**
     * @param OrderEntity $order
     * @param string $mandateId
     * @param Context $context
     * @throws CustomerCouldNotBeFoundException
     * @return void
     */
    public function confirmSubscription(OrderEntity $order, string $mandateId, Context $context): void
    {
        if (!$this->isSubscriptionFeatureEnabled($order)) {
            return;
        }

        # -------------------------------------------------------------------------------------
        # VERIFY IF SUBSCRIPTION ORDER!!!!!

        # load all pending subscriptions of the order.
        # we will now make sure to create Mollie subscriptions and prepare everything for recurring payments.
        # Attention -> if this is a casual order (no subscription) it will just find NOTHING!
        # so return in that case
        $pendingSubscriptions = $this->getRepository()->findPendingSubscriptions($order->getId(), $context);

        # if we have nothing to confirm, then just return
        if (count($pendingSubscriptions) <= 0) {
            return;
        }

        # -------------------------------------------------------------------------------------
        # WE AT LEAST HAVE A SUBSCRIPTION ORDER


        # if we have something to confirm (and create) but we don't
        # have a valid mandate ID, then throw an exception
        if (empty($mandateId)) {
            throw new \Exception('Cannot confirm subscription for order: ' . $order->getOrderNumber() . '! Provided mandate ID of payment is empty!');
        }

        if (!$order->getOrderCustomer() instanceof OrderCustomerEntity) {
            throw new \Exception('Order: ' . $order->getOrderNumber() . ' does not have a linked customer');
        }

        $this->getLogger()->debug('Confirming pending subscription for order: ' . $order->getOrderNumber());


        # first get our mollie customer ID from the order.
        # this is required to create a subscription
        $mollieCustomerId = $this->getCustomers()->getMollieCustomerId(
            (string)$order->getOrderCustomer()->getCustomerId(),
            $order->getSalesChannelId(),
            $context
        );

        # -------------------------------------------------------------------------------------

        # we only support 1 subscription of an order!
        if ($pendingSubscriptions->count() > 1) {
            $this->getLogger()->warning('Attention, multiple subscriptions exist for order ' . $order->getOrderNumber() . ' when trying to confirm it. This is not supported and should not be possible.');
        }

        /** @var SubscriptionEntity $pendingSubscription */
        $pendingSubscription = $pendingSubscriptions->first();

        # -------------------------------------------------------------------------------------

        $metaData = $pendingSubscription->getMetadata();

        $jsonPayload = $this->getPayloadBuilder()->buildRequestPayload(
            $pendingSubscription,
            $metaData->getStartDate(),
            (string)$metaData->getInterval(),
            $metaData->getIntervalUnit(),
            (int)$metaData->getTimes(),
            $mandateId
        );

        # create the subscription in Mollie.
        # this is important to really start the subscription process
        $gateway = $this->getMollieGateway($pendingSubscription);
        $mollieSubscription = $gateway->createSubscription($mollieCustomerId, $jsonPayload);

        # -------------------------------------------------------------------------------------

        $oldStatus = $pendingSubscription->getStatus();
        $newStatus = SubscriptionStatus::fromMollieStatus($mollieSubscription->status);

        # confirm the subscription in our local database
        # by adding the missing external Mollie IDs
        $this->getRepository()->confirmNewSubscription(
            $pendingSubscription->getId(),
            (string)$mollieSubscription->id,
            $newStatus,
            (string)$mollieSubscription->customerId,
            $mandateId,
            (string)$mollieSubscription->nextPaymentDate,
            $context
        );


        # -------------------------------------------------------------------------------------

        # fetch latest data again, just to be safe
        $finalSubscription = $this->getRepository()->findById($pendingSubscription->getId(), $context);


        # also add a history entry for this subscription
        $this->getStatusHistory()->markConfirmed($finalSubscription, $oldStatus, $newStatus, $context);

        # FLOW BUILDER / BUSINESS EVENTS
        $event = $this->getFlowBuilderEventFactory()->buildSubscriptionStartedEvent($finalSubscription->getCustomer(), $finalSubscription, $context);
        $this->getFlowBuilderDispatcher()->dispatch($event);
    }
}
