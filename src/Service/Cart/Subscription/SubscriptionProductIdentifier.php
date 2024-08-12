<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Cart\Subscription;

use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CheckoutCartLineItem;

class SubscriptionProductIdentifier
{
    /**
     * Checks if a line item is a subscription product.
     *
     * @param CheckoutCartLineItem $lineItem
     * @return bool
     */
    public function isSubscriptionProduct(CheckoutCartLineItem $lineItem): bool
    {
        return (new LineItemAttributes($lineItem))->isSubscriptionProduct();
    }
}
