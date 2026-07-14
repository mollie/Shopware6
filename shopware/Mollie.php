<?php
declare(strict_types=1);

namespace Mollie\Shopware;

final class Mollie
{
    public const EXTENSION = 'mollie_payments';
    public const ROUNDING_PRECISION = 2;

    /**
     * Line item payload flag that marks a line item as the subscription variant of a product.
     * Set by the storefront "Subscribe" button so a product that is also purchasable one-off
     * can live in the cart as two distinct line items.
     */
    public const SUBSCRIPTION_PAYLOAD_KEY = 'mollieSubscription';

    /**
     * Prefix for the line item id of the subscription variant, so it does not merge with the
     * one-off line item (Shopware merges line items by id, not by referencedId).
     */
    public const SUBSCRIPTION_LINE_ITEM_PREFIX = 'mollie-subscription-';

    /**
     * Request flag carrying the product id that should be added as a subscription. Sent by the
     * storefront "Subscribe" button and read by the cart-item-add route decorator, which then
     * rewrites the matching product line item into its subscription variant.
     */
    public const SUBSCRIBE_REQUEST_KEY = 'mollieSubscribe';
}
