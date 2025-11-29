<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Cart\LineItem\LineItem as ShopwareLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

/**
 * Cannot use enums becaue of min php version 8.0
 */
final class LineItemType extends AbstractEnum
{
    public const PHYSICAL = 'physical';
    public const DIGITAL = 'digital';
    public const SHIPPING = 'shipping_fee';
    public const DISCOUNT = 'discount';
    public const CREDIT = 'store_credit';
    public const GIFT_CARD = 'gift_card';
    public const SURCHARGE = 'surcharge';
    public const LINE_ITEM_TYPE_CUSTOM_PRODUCTS = 'customized-products';
    public const POSSIBLE_TYPES = [
        self::PHYSICAL,
        self::DIGITAL,
        self::SHIPPING,
        self::DISCOUNT,
        self::CREDIT,
        self::GIFT_CARD,
        self::SURCHARGE,
    ];

    private const SHOPWARE_TYPE_MAPPING = [
        ShopwareLineItem::PRODUCT_LINE_ITEM_TYPE => self::PHYSICAL,
        ShopwareLineItem::CREDIT_LINE_ITEM_TYPE => self::CREDIT,
        ShopwareLineItem::PROMOTION_LINE_ITEM_TYPE => self::DISCOUNT,
        self::LINE_ITEM_TYPE_CUSTOM_PRODUCTS => self::PHYSICAL,
    ];

    public function __construct(string $type = self::PHYSICAL)
    {
        parent::__construct($type);
    }

    public static function fromOderLineItem(OrderLineItemEntity $orderLineItem): self
    {
        $type = self::SHOPWARE_TYPE_MAPPING[$orderLineItem->getType()] ?? self::DIGITAL;

        return new self($type);
    }

    /**
     * @return string[]
     */
    protected function getPossibleValues(): array
    {
        return self::POSSIBLE_TYPES;
    }
}
