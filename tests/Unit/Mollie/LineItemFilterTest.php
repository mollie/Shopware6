<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\ItemFilter\Subscriber\CustomizedProductsSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\DreiscProductSetSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\EasyCouponSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\GiftConfiguratorSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\NetiBundleSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\RepertusProductSetSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\SwkwebProductSetSubscriber;
use Mollie\Shopware\Component\ItemFilter\Subscriber\ZeobvBundleSubscriber;
use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Mollie\Shopware\Component\Mollie\LineItemFilter;
use Mollie\Shopware\Unit\Builder\LineItemFilterBuilder;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection as CartLineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(LineItemFilter::class)]
#[CoversClass(FilterLineItemEvent::class)]
#[CoversClass(CustomizedProductsSubscriber::class)]
#[CoversClass(DreiscProductSetSubscriber::class)]
#[CoversClass(EasyCouponSubscriber::class)]
#[CoversClass(GiftConfiguratorSubscriber::class)]
#[CoversClass(NetiBundleSubscriber::class)]
#[CoversClass(RepertusProductSetSubscriber::class)]
#[CoversClass(SwkwebProductSetSubscriber::class)]
#[CoversClass(ZeobvBundleSubscriber::class)]
class LineItemFilterTest extends TestCase
{
    private LineItemFilter $lineItemFilter;
    private OrderEntityBuilder $orderBuilder;

    protected function setUp(): void
    {
        $this->lineItemFilter = LineItemFilterBuilder::build();
        $this->orderBuilder = new OrderEntityBuilder();
    }

    // -------------------------------------------------------------------------
    // filterOrderItems
    // -------------------------------------------------------------------------

    /**
     * Regular products pass through unchanged.
     */
    public function testRegularProductIsKept(): void
    {
        $item = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 10.0);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$item]));

        $this->assertCount(1, $result);
    }

    /**
     * A product tagged as zeobv bundle parent must be removed.
     * Its children (separate items without the payload key) must stay.
     */
    public function testZeobvBundleParentIsSkipped(): void
    {
        $parent = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 50.0);
        $parent->setPayload(['zeobvProductsInBundle' => ['child-1', 'child-2']]);

        $child1 = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 25.0);
        $child2 = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 25.0);

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
        $parent = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 40.0);
        $parent->setPayload(['is-neti-bundle' => true]);

        $child = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 40.0);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$parent, $child]));

        $this->assertCount(1, $result);
    }

    /**
     * Repertus set containers must be filtered.
     */
    public function testRepertusContainerIsSkipped(): void
    {
        $container = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), 'repertus_product_container', 0.0);
        $child = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 20.0);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$container, $child]));

        $this->assertCount(1, $result);
        $this->assertSame($child->getId(), $result->first()->getId());
    }

    /**
     * DreíSec set containers must be filtered.
     */
    public function testDreiscContainerIsSkipped(): void
    {
        $container = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), 'dreisc-set', 0.0);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$container]));

        $this->assertCount(0, $result);
    }

    /**
     * SKWeb set containers must be filtered.
     */
    public function testSkwebContainerIsSkipped(): void
    {
        $container = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), 'swkweb-product-set', 0.0);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$container]));

        $this->assertCount(0, $result);
    }

    /**
     * The customized-products wrapper must be filtered; its product child stays.
     */
    public function testCustomizedProductsContainerIsSkipped(): void
    {
        $container = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), 'customized-products', 0.0);
        $productChild = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 15.0);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$container, $productChild]));

        $this->assertCount(1, $result);
        $this->assertSame($productChild->getId(), $result->first()->getId());
    }

    /**
     * Customized-product options with price > 0 must be kept.
     */
    public function testCustomizedProductOptionWithPriceIsKept(): void
    {
        $option = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), 'customized-products-option', 5.0);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$option]));

        $this->assertCount(1, $result);
    }

    /**
     * Customized-product options with price = 0 must be filtered.
     */
    public function testCustomizedProductOptionWithZeroPriceIsSkipped(): void
    {
        $option = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), 'customized-products-option', 0.0);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$option]));

        $this->assertCount(0, $result);
    }

    /**
     * A gift-configurator parent (carrying the configuratorToken) must be removed,
     * just like a zeobv / NetI bundle parent.
     */
    public function testGiftConfiguratorParentIsSkipped(): void
    {
        $parent = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 30.0);
        $parent->setPayload(['configuratorToken' => 'token-123']);

        $product = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 20.0);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$parent, $product]));

        $this->assertCount(1, $result);
        $this->assertSame($product->getId(), $result->first()->getId());
    }

    /**
     * A NetiNextEasyCoupon voucher-product parent must be removed. Its voucher and
     * service-fee children carry the actual price and stay in the payload.
     */
    public function testEasyCouponVoucherParentIsSkipped(): void
    {
        $parent = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 497.95);
        $parent->setPayload(['netiNextEasyCoupon' => ['voucherValue' => 25.0]]);

        $voucherChild = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), 'easy-coupon-extra-option-voucher', 495.95);
        $serviceChild = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), 'easy-coupon-extra-option', 2.0);
        $postalChild = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), 'easy-coupon-extra-option-postal', 1.5);

        $result = $this->filterOrderItems(new OrderLineItemCollection([$parent, $voucherChild, $serviceChild, $postalChild]));

        $this->assertCount(3, $result);
        $ids = array_keys(iterator_to_array($result));
        $this->assertNotContains($parent->getId(), $ids);
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
        $container = new CartLineItem(Uuid::randomHex(), 'customized-products');

        $result = $this->filterCartItems(new CartLineItemCollection([$container]));

        $this->assertCount(0, $result);
    }

    /**
     * Customized-product option with price > 0 is kept in cart.
     */
    public function testCartCustomizedProductOptionWithPriceIsKept(): void
    {
        $option = new CartLineItem(Uuid::randomHex(), 'customized-products-option');
        $option->setPrice($this->makeCartPrice(5.0));

        $result = $this->filterCartItems(new CartLineItemCollection([$option]));

        $this->assertCount(1, $result);
    }

    /**
     * Customized-product option with price = 0 is filtered from cart.
     */
    public function testCartCustomizedProductOptionWithZeroPriceIsSkipped(): void
    {
        $option = new CartLineItem(Uuid::randomHex(), 'customized-products-option');
        $option->setPrice($this->makeCartPrice(0.0));

        $result = $this->filterCartItems(new CartLineItemCollection([$option]));

        $this->assertCount(0, $result);
    }

    /**
     * A cart option that is not calculated yet has no price and must be filtered.
     */
    public function testCartCustomizedProductOptionWithoutPriceIsSkipped(): void
    {
        $option = new CartLineItem(Uuid::randomHex(), 'customized-products-option');

        $result = $this->filterCartItems(new CartLineItemCollection([$option]));

        $this->assertCount(0, $result);
    }

    /**
     * Gift-configurator parent (carrying the configuratorToken) in cart must be removed.
     */
    public function testCartGiftConfiguratorParentIsSkipped(): void
    {
        $parent = new CartLineItem(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE);
        $parent->setPayload(['configuratorToken' => 'token-123']);

        $product = new CartLineItem(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE);
        $product->setPrice($this->makeCartPrice(20.0));

        $result = $this->filterCartItems(new CartLineItemCollection([$parent, $product]));

        $this->assertCount(1, $result);
        $this->assertSame($product->getId(), $result->first()->getId());
    }

    /**
     * NetiNextEasyCoupon voucher-product parent in cart must be removed.
     */
    public function testCartEasyCouponVoucherParentIsSkipped(): void
    {
        $parent = new CartLineItem(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE);
        $parent->setPayload(['netiNextEasyCoupon' => ['voucherValue' => 25.0]]);
        $parent->setPrice($this->makeCartPrice(497.95));

        $voucherChild = new CartLineItem(Uuid::randomHex(), 'easy-coupon-extra-option-voucher');
        $voucherChild->setPrice($this->makeCartPrice(495.95));

        $result = $this->filterCartItems(new CartLineItemCollection([$parent, $voucherChild]));

        $this->assertCount(1, $result);
        $this->assertSame($voucherChild->getId(), $result->first()->getId());
    }

    /**
     * A listener of a foreign plugin that throws must not break the payload, the item is kept.
     */
    public function testItemIsKeptWhenAListenerFails(): void
    {
        $logger = new FakeLogger();
        $item = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), CartLineItem::PRODUCT_LINE_ITEM_TYPE, 10.0);
        $lineItemFilter = new LineItemFilter($this->failingDispatcher(), $logger);

        $isAllowed = $lineItemFilter->isItemAllowed($item);

        $this->assertTrue($isAllowed);
        $record = $logger->getRecords()[0];
        $this->assertSame('error', $record['level']);
        $this->assertSame($item->getId(), $record['context']['lineItemId']);
        $this->assertSame('the listener of a foreign plugin is broken', $record['context']['error']);
    }

    /**
     * A listener that throws after another one vetoed the item must not bring the item back.
     */
    public function testItemStaysFilteredWhenALaterListenerFails(): void
    {
        $eventDispatcher = $this->failingDispatcher();
        $eventDispatcher->addSubscriber(new DreiscProductSetSubscriber());
        $container = $this->orderBuilder->createOrderLineItemWithType(Uuid::randomHex(), 'dreisc-set', 0.0);
        $lineItemFilter = new LineItemFilter($eventDispatcher, new FakeLogger());

        $isAllowed = $lineItemFilter->isItemAllowed($container);

        $this->assertFalse($isAllowed);
    }

    /**
     * The delivery discount placeholder is decided without the listeners and survives a broken one.
     */
    public function testDeliveryDiscountPlaceholderIsSkippedWithoutTheListeners(): void
    {
        $placeholder = $this->orderBuilder->getDeliveryDiscountPromotionLineItem('Free shipping');
        $lineItemFilter = new LineItemFilter($this->failingDispatcher(), new FakeLogger());

        $isAllowed = $lineItemFilter->isItemAllowed($placeholder);

        $this->assertFalse($isAllowed);
    }

    private function failingDispatcher(): EventDispatcher
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(FilterLineItemEvent::class, static function (): void {
            throw new \RuntimeException('the listener of a foreign plugin is broken');
        }, -100);

        return $eventDispatcher;
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
