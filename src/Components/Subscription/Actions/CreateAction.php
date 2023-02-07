<?php

namespace Kiener\MolliePayments\Components\Subscription\Actions;

use Exception;
use Kiener\MolliePayments\Components\Subscription\Actions\Base\BaseAction;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CreateAction extends BaseAction
{
    private const INITIAL_STATUS = SubscriptionStatus::PENDING;


    /**
     * @param OrderEntity $order
     * @param SalesChannelContext $context
     * @throws Exception
     * @return string
     */
    public function createSubscription(OrderEntity $order, SalesChannelContext $context): string
    {
        if (!$this->isSubscriptionFeatureEnabled($order)) {
            return '';
        }

        # -------------------------------------------------------------------------------------

        if ($order->getLineItems() === null || $order->getLineItems()->count() > 1) {
            # Mixed carts are not allowed for subscriptions
            return '';
        }

        $item = $order->getLineItems()->first();

        if (!$item instanceof OrderLineItemEntity) {
            throw new Exception('No line item entity found for order ' . $order->getOrderNumber());
        }

        # ------------------------------------------------------------------------------------------------------------------------

        $attributes = new OrderLineItemEntityAttributes($item);

        if (!$attributes->isSubscriptionProduct()) {
            # Mixed carts are not allowed for subscriptions
            return '';
        }

        if ($attributes->getSubscriptionInterval() <= 0) {
            throw new Exception('Invalid subscription interval unit');
        }

        if (empty($attributes->getSubscriptionIntervalUnit())) {
            throw new Exception('Invalid subscription interval unit');
        }

        # ------------------------------------------------------------------------------------------------------------------------

        $this->getLogger()->debug('Creating subscription entry for order: ' . $order->getOrderNumber());

        $subscription = $this->getSubscriptionBuilder()->buildSubscription($order);

        $this->getRepository()->insertSubscription($subscription, self::INITIAL_STATUS, $context->getContext());


        # fetch subscription again, to have correct data like createAt and more
        $subscription = $this->getRepository()->findById($subscription->getId(), $context->getContext());


        $this->getStatusHistory()->markCreated($subscription, self::INITIAL_STATUS, $context->getContext());

        return $subscription->getId();
    }
}
