<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\Struct\Struct;

final class LineItemFilter implements LineItemFilterInterface
{
    public const TYPE_CUSTOM_PRODUCTS = 'customized-products';
    public const TYPE_CUSTOM_PRODUCTS_OPTION = 'customized-products-option';

    private const CONTAINER_TYPES = [
        'repertus_product_container',
        'dreisc-set',
        'swkweb-product-set',
        self::TYPE_CUSTOM_PRODUCTS,
    ];

    /**
     * Decide whether a line item should be part of the Mollie API payload:
     * - removes set-product containers (repertus, dreisc, skweb)
     * - removes customized-products containers (their product child stays in)
     * - removes customized-product options with price = 0
     * - removes zeobv / NetI bundle parents and gift-configurator parents (children are already in the flat list)
     *
     * @param CartLineItem|OrderLineItemEntity $item
     */
    public function isItemAllowed(Struct $item): bool
    {
        $type = (string) $item->getType();
        $payload = $item->getPayload() ?? [];

        if (in_array($type, self::CONTAINER_TYPES, true)) {
            return false;
        }

        if ($type === self::TYPE_CUSTOM_PRODUCTS_OPTION) {
            $price = $item->getPrice();

            return $price !== null && $price->getTotalPrice() > 0;
        }

        if ($type === CartLineItem::PRODUCT_LINE_ITEM_TYPE) {
            return ! $this->isBundleParent($payload);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isBundleParent(array $payload): bool
    {
        $bundleProducts = $payload['zeobvProductsInBundle'] ?? [];
        $isNetIBundle = $payload['is-neti-bundle'] ?? false;
        $isGiftConfigurator = isset($payload['configuratorToken']);

        return (is_array($bundleProducts) && count($bundleProducts) > 0)
            || $isNetIBundle === true
            || $isGiftConfigurator;
    }
}
