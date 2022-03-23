<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Builder;

use Exception;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Setting\Source\RepetitionType;
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

        foreach ($order->getLineItems() as $orderItem) {

            $payload = $orderItem->getPayload();
            $customFields = $payload['customFields'];

            if (!array_key_exists('mollie_subscription', $customFields) || !array_key_exists('mollie_subscription_product', $customFields['mollie_subscription']) || !$customFields['mollie_subscription']['mollie_subscription_product']) {
                continue;
            }

            $subscriptions[] = $this->buildItemSubscription($orderItem, $order);
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
        $customFields = $lineItem->getPayload()['customFields'];

        $intervalAmount = (string)$customFields["mollie_subscription"]['mollie_subscription_interval_amount'];
        $intervalType = (string)$customFields["mollie_subscription"]['mollie_subscription_interval_type'];
        $repetitionType = (string)$customFields["mollie_subscription"]['mollie_subscription_repetition_type'];

        $repetitionAmount = 0;

        if ($repetitionType === RepetitionType::INFINITE) {
            $repetitionAmount = '';
        }


        $entity = new SubscriptionEntity(
            $order->getOrderNumber() . ': ' . $lineItem->getLabel(),
            $lineItem->getTotalPrice(),
            $order->getCurrency()->getIsoCode(),
            $lineItem->getProductId(),
            $order->getOrderDateTime()->format('Y-m-d'),
            $intervalAmount,
            $intervalType,
            $repetitionAmount,
            $order->getId(),
            $order->getSalesChannelId(),
        );

        # create a new shopware ID
        $entity->setId(Uuid::randomHex());

        return $entity;
    }

}
