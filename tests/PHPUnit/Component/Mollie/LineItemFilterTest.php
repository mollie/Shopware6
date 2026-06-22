<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Component\Mollie;

use Mollie\Shopware\Component\Mollie\LineItemFilter;
use MolliePayments\Shopware\Tests\Traits\OrderTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection as CartLineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Uuid\Uuid;

class LineItemFilterTest extends TestCase
{
    use OrderTrait;

    private LineItemFilter $lineItemFilter;

    protected function setUp(): void
    {
        $this->lineItemFilter = new LineItemFilter();
    }

    // -------------------------------------------------------------------------
    // filterOrderItems
    // -------------------------------------------------------------------------

    /**
     * Regular products pass through unchanged.
     */
    public function testRegularProductIsKept(): void
    {
        $item = $this->getOrderLineItem(Uuid::randomHex(), 'SW-001', 'T-Shirt', 1, 10.0, 19, 1.9);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$item]));

        $this->assertCount(1, $result);
    }

    /**
     * A product tagged as zeobv bundle parent must be removed.
     * Its children (separate items without the payload key) must stay.
     */
    public function testZeobvBundleParentIsSkipped(): void
    {
        $parent = $this->getOrderLineItem(Uuid::randomHex(), 'BUNDLE', 'Bundle Parent', 1, 50.0, 19, 9.5);
        $parent->setPayload(['zeobvProductsInBundle' => ['child-1', 'child-2']]);

        $child1 = $this->getOrderLineItem(Uuid::randomHex(), 'SW-A', 'Bundle Product A', 1, 25.0, 19, 4.75);
        $child2 = $this->getOrderLineItem(Uuid::randomHex(), 'SW-B', 'Bundle Product B', 1, 25.0, 19, 4.75);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$parent, $child1, $child2]));

        $this->assertCount(2, $result);
        $ids = array_keys(iterator_to_array($result));
        $this->assertNotContains($parent->getId(), $ids);
    }

    /**
     * A product tagged as NetI bundle parent must be removed.
     */
    public function testNetiBundleParentIsSkipped(): void
    {
        $parent = $this->getOrderLineItem(Uuid::randomHex(), 'NETI', 'NetI Bundle', 1, 40.0, 19, 7.6);
        $parent->setPayload(['is-neti-bundle' => true]);

        $child = $this->getOrderLineItem(Uuid::randomHex(), 'SW-C', 'NetI Product', 1, 40.0, 19, 7.6);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$parent, $child]));

        $this->assertCount(1, $result);
    }

    /**
     * Repertus set containers must be filtered.
     */
    public function testRepertusContainerIsSkipped(): void
    {
        $container = $this->getOrderLineItem(Uuid::randomHex(), '', 'Repertus Set', 1, 0.0, 0, 0, 'repertus_product_container');
        $child = $this->getOrderLineItem(Uuid::randomHex(), 'SW-D', 'Set Product', 1, 20.0, 19, 3.8);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$container, $child]));

        $this->assertCount(1, $result);
        $this->assertSame($child->getId(), $result->first()->getId());
    }

    /**
     * DreíSec set containers must be filtered.
     */
    public function testDreiscContainerIsSkipped(): void
    {
        $container = $this->getOrderLineItem(Uuid::randomHex(), '', 'DreiSec Set', 1, 0.0, 0, 0, 'dreisc-set');

        $result = $this->filterOrderItems(new OrderLineItemCollection([$container]));

        $this->assertCount(0, $result);
    }

    /**
     * SKWeb set containers must be filtered.
     */
    public function testSkwebContainerIsSkipped(): void
    {
        $container = $this->getOrderLineItem(Uuid::randomHex(), '', 'SKWeb Set', 1, 0.0, 0, 0, 'swkweb-product-set');

        $result = $this->filterOrderItems(new OrderLineItemCollection([$container]));

        $this->assertCount(0, $result);
    }

    /**
     * The customized-products wrapper must be filtered; its product child stays.
     */
    public function testCustomizedProductsContainerIsSkipped(): void
    {
        $container = $this->getOrderLineItem(Uuid::randomHex(), '', 'Customized Mug', 1, 0.0, 0, 0, 'customized-products');
        $productChild = $this->getOrderLineItem(Uuid::randomHex(), 'SW-E', 'Mug (base)', 1, 15.0, 19, 2.85);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$container, $productChild]));

        $this->assertCount(1, $result);
        $this->assertSame($productChild->getId(), $result->first()->getId());
    }

    /**
     * Customized-product options with price > 0 must be kept.
     */
    public function testCustomizedProductOptionWithPriceIsKept(): void
    {
        $option = $this->getOrderLineItem(Uuid::randomHex(), '', 'Engraving', 1, 5.0, 19, 0.95, 'customized-products-option');

        $result = $this->filterOrderItems(new OrderLineItemCollection([$option]));

        $this->assertCount(1, $result);
    }

    /**
     * Customized-product options with price = 0 must be filtered.
     */
    public function testCustomizedProductOptionWithZeroPriceIsSkipped(): void
    {
        $option = $this->getOrderLineItem(Uuid::randomHex(), '', 'Color choice', 1, 0.0, 0, 0.0, 'customized-products-option');

        $result = $this->filterOrderItems(new OrderLineItemCollection([$option]));

        $this->assertCount(0, $result);
    }

    // -------------------------------------------------------------------------
    // filterCartItems
    // -------------------------------------------------------------------------

    /**
     * Regular cart products pass through.
     */
    public function testCartRegularProductIsKept(): void
    {
        $item = new CartLineItem(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE);
        $item->setPrice($this->makeCartPrice(10.0));

        $result = $this->filterCartItems(new CartLineItemCollection([$item]));

        $this->assertCount(1, $result);
    }

    /**
     * zeobv bundle parent in cart must be removed.
     */
    public function testCartZeobvBundleParentIsSkipped(): void
    {
        $parent = new CartLineItem(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE);
        $parent->setPayload(['zeobvProductsInBundle' => ['a', 'b']]);

        $child = new CartLineItem(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE);

        $result = $this->filterCartItems(new CartLineItemCollection([$parent, $child]));

        $this->assertCount(1, $result);
        $this->assertSame($child->getId(), $result->first()->getId());
    }

    /**
     * NetI bundle parent in cart must be removed.
     */
    public function testCartNetiBundleParentIsSkipped(): void
    {
        $parent = new CartLineItem(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE);
        $parent->setPayload(['is-neti-bundle' => true]);

        $result = $this->filterCartItems(new CartLineItemCollection([$parent]));

        $this->assertCount(0, $result);
    }

    /**
     * Customized-products container in cart must be filtered.
     */
    public function testCartCustomizedProductsContainerIsSkipped(): void
    {
        $container = new CartLineItem(Uuid::randomHex(), LineItemFilter::TYPE_CUSTOM_PRODUCTS);

        $result = $this->filterCartItems(new CartLineItemCollection([$container]));

        $this->assertCount(0, $result);
    }

    /**
     * Customized-product option with price > 0 is kept in cart.
     */
    public function testCartCustomizedProductOptionWithPriceIsKept(): void
    {
        $option = new CartLineItem(Uuid::randomHex(), LineItemFilter::TYPE_CUSTOM_PRODUCTS_OPTION);
        $option->setPrice($this->makeCartPrice(5.0));

        $result = $this->filterCartItems(new CartLineItemCollection([$option]));

        $this->assertCount(1, $result);
    }

    /**
     * Customized-product option with price = 0 is filtered from cart.
     */
    public function testCartCustomizedProductOptionWithZeroPriceIsSkipped(): void
    {
        $option = new CartLineItem(Uuid::randomHex(), LineItemFilter::TYPE_CUSTOM_PRODUCTS_OPTION);
        $option->setPrice($this->makeCartPrice(0.0));

        $result = $this->filterCartItems(new CartLineItemCollection([$option]));

        $this->assertCount(0, $result);
    }

    private function filterOrderItems(OrderLineItemCollection $items): OrderLineItemCollection
    {
        return $items->filter($this->lineItemFilter->isItemAllowed(...));
    }

    private function filterCartItems(CartLineItemCollection $items): CartLineItemCollection
    {
        return $items->filter($this->lineItemFilter->isItemAllowed(...));
    }

    private function makeCartPrice(float $total): CalculatedPrice
    {
        return new CalculatedPrice($total, $total, new CalculatedTaxCollection(), new TaxRuleCollection(), 1);
    }
}
