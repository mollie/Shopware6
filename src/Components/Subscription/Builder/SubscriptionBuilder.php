<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Builder;

use Exception;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Setting\Source\RepetitionType;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;


class SubscriptionBuilder
{

    /**
     * @param OrderEntity $order
     * @return SubscriptionEntity[]
     * @throws Exception
     */
    public function buildSubscriptions(OrderEntity $order): array
    {
        $subscriptions = [];

        foreach ($order->getLineItems() as $lineItem) {

            $attributes = new OrderLineItemEntityAttributes($lineItem);

            if ($attributes->getSubscriptionInterval() <= 0) {
                continue;
            }

            $subscriptions[] = $this->buildItemSubscription($lineItem, $order);
        }

        return $subscriptions;
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


        $entity = new SubscriptionEntity();
        $entity->setId(Uuid::randomHex());

        $entity->setDescription($order->getOrderNumber() . ': ' . $lineItem->getLabel());
        $entity->setAmount($lineItem->getTotalPrice());
        $entity->setCurrency($order->getCurrency()->getIsoCode());
        $entity->setAmount($lineItem->getTotalPrice());

        $entity->setCustomerId($order->getOrderCustomer()->getId());
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
