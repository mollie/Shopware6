<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\Validator;

use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;

class MixedOrderValidator
{
    /**
     * @param OrderEntity $order
     * @return bool
     */
    public function isMixedCart(OrderEntity $order): bool
    {
        $subscriptionItemsCount = 0;
        $otherItemsCount = 0;
        $isMixedCart = false;

        $lineItems = $order->getLineItems();

        if (!$lineItems instanceof OrderLineItemCollection) {
            return false;
        }

        foreach ($lineItems->getElements() as $lineItem) {
            $attributes = new OrderLineItemEntityAttributes($lineItem);

            if ($attributes->isSubscriptionProduct()) {
                $subscriptionItemsCount++;
            } else {
                # promotions are ok
                $otherItemsCount++;
            }

            if ($otherItemsCount > 0) {
                # mixed cart with other items
                $isMixedCart = true;
            }

            if ($subscriptionItemsCount > 1) {
                # mixed cart with multiple subscription items
                $isMixedCart = true;
            }
        }

        if ($subscriptionItemsCount >= 1 && $isMixedCart) {
            return true;
        }

        return false;
    }
}
