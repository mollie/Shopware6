<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\Validator;

use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Shopware\Core\Checkout\Cart\Cart;

class MixedCartValidator
{
    /**
     * @param Cart $cart
     * @return bool
     */
    public function isMixedCart(Cart $cart): bool
    {
        $subscriptionItemsCount = 0;
        $otherItemsCount = 0;
        $isMixedCart = false;

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $attributes = new LineItemAttributes($lineItem);

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
