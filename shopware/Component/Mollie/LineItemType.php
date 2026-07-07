<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

enum LineItemType: string
{
    case PHYSICAL = 'physical';
    case DIGITAL = 'digital';
    case SHIPPING = 'shipping_fee';
    case DISCOUNT = 'discount';
    case CREDIT = 'store_credit';
    case GIFT_CARD = 'gift_card';
    case SURCHARGE = 'surcharge';

    case LINE_ITEM_TYPE_CUSTOM_PRODUCTS = 'customized-products';

    public static function fromOderLineItem(OrderLineItemEntity $orderLineItem): self
    {
        $oderLineItemType = (string) $orderLineItem->getType();

        $type = match ($oderLineItemType) {
            LineItem::PRODUCT_LINE_ITEM_TYPE, self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS->value => self::PHYSICAL,
            LineItem::CREDIT_LINE_ITEM_TYPE => self::CREDIT,
            LineItem::PROMOTION_LINE_ITEM_TYPE => self::DISCOUNT,
            default => self::DIGITAL,
        };

        if ($type === self::CREDIT || $type === self::GIFT_CARD) {
            return $type;
        }

        // discounts added by third-party plugins have custom line item types, Mollie rejects
        // negative amounts unless the type is discount, store_credit or gift_card
        $price = $orderLineItem->getPrice();
        if ($price instanceof CalculatedPrice && $price->getTotalPrice() < 0) {
            return self::DISCOUNT;
        }

        return $type;
    }
}
