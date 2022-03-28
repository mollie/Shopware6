<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Services\Builder;

use Exception;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\RepetitionType;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;


class SubscriptionBuilder
{

    /**
     * @param OrderEntity $order
     * @return SubscriptionEntity
     * @throws Exception
     */
    public function buildSubscription(OrderEntity $order): SubscriptionEntity
    {
        $item = $order->getLineItems()->first();

        return $this->buildItemSubscription($item, $order);
    }

    /**
     * @param OrderLineItemEntity $lineItem
     * @param OrderEntity $order
     * @return SubscriptionEntity
     */
    private function buildItemSubscription(OrderLineItemEntity $lineItem, OrderEntity $order): SubscriptionEntity
    {
        $attributes = new OrderLineItemEntityAttributes($lineItem);

        $interval = $attributes->getSubscriptionInterval();
        $intervalUnit = $attributes->getSubscriptionIntervalUnit();

        $times = $attributes->getSubscriptionRepetitionCount();
        $repetitionType = $attributes->getSubscriptionRepetitionType();

        if ($repetitionType === RepetitionType::INFINITE) {
            $times = null;
        }

        $description = $lineItem->getQuantity() . 'x ' . $lineItem->getLabel() . ' (Order #' . $order->getOrderNumber() . ', ' . $lineItem->getTotalPrice() . ' ' . $order->getCurrency()->getIsoCode() . ')';

        # -----------------------------------------------------------------------------------------

        $entity = new SubscriptionEntity();
        $entity->setId(Uuid::randomHex());

        $entity->setDescription($description);

        # ATTENTION
        # the amount needs to be the total amount of our order
        # and not the price amount. because it would have shipping as well
        # as promotions.  because we only offer subscriptions as a 1-item order without mixed carts,
        # this is the perfect way to still have shopware doing every calculation.
        $entity->setAmount($order->getAmountTotal());
        $entity->setCurrency($order->getCurrency()->getIsoCode());

        $entity->setQuantity($lineItem->getQuantity());

        $entity->setCustomerId($order->getOrderCustomer()->getCustomerId());
        $entity->setProductId($lineItem->getProductId());
        $entity->setOrderId($order->getId());
        $entity->setSalesChannelId($order->getSalesChannelId());

        $entity->setMetadata(
            $order->getOrderDateTime()->format('Y-m-d'),
            $interval,
            $intervalUnit,
            $times
        );

        return $entity;
    }

}
