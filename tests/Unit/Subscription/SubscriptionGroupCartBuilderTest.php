<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Component\Subscription\RenewalAddresses;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCartBuilder;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use Mollie\Shopware\Unit\Subscription\Fake\FakeLineItemFactoryRegistry;
use Mollie\Shopware\Unit\Subscription\Fake\FakeOrderConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

/**
 * Builds the cart a subscription renewal is charged from: the products of one interval group,
 * priced in the original order's sales channel context.
 */
#[CoversClass(SubscriptionGroupCartBuilder::class)]
final class SubscriptionGroupCartBuilderTest extends TestCase
{
    private const MONTHLY = '1 month';

    private FakeCartService $cartService;

    private FakeOrderConverter $orderConverter;

    private FakeLineItemFactoryRegistry $lineItemFactory;

    protected function setUp(): void
    {
        $this->cartService = new FakeCartService(CartBuilder::create()->build());
        $this->orderConverter = new FakeOrderConverter(new FakeSalesChannelContext());
        $this->lineItemFactory = new FakeLineItemFactoryRegistry();
    }

    public function testTheProductsOfTheIntervalGroupEndUpInTheCart(): void
    {
        $order = $this->order([
            $this->subscriptionLineItem('line-1', 'product-1', 2, new Interval(1, IntervalUnit::MONTHS)),
            $this->subscriptionLineItem('line-2', 'product-2', 1, new Interval(1, IntervalUnit::MONTHS)),
        ]);

        $groupCart = $this->builder()->buildGroupCart($order, self::MONTHLY, Context::createDefaultContext());

        $this->assertNotNull($groupCart);
        $this->assertSame(2, $groupCart->getCart()->getLineItems()->count());
    }

    /**
     * A weekly and a monthly subscription in one order become two separate Mollie subscriptions,
     * so only the requested interval may reach the cart.
     */
    public function testAProductOfAnotherIntervalIsNotInTheCart(): void
    {
        $order = $this->order([
            $this->subscriptionLineItem('line-1', 'product-1', 1, new Interval(1, IntervalUnit::MONTHS)),
            $this->subscriptionLineItem('line-2', 'product-2', 1, new Interval(2, IntervalUnit::WEEKS)),
        ]);

        $groupCart = $this->builder()->buildGroupCart($order, self::MONTHLY, Context::createDefaultContext());

        $this->assertNotNull($groupCart);
        $this->assertSame(['product-1'], array_column($this->lineItemFactory->getCreatedFrom(), 'referencedId'));
    }

    public function testTheOrderedQuantityIsCarriedOverToTheRenewal(): void
    {
        $order = $this->order([$this->subscriptionLineItem('line-1', 'product-1', 3, new Interval(1, IntervalUnit::MONTHS))]);

        $this->builder()->buildGroupCart($order, self::MONTHLY, Context::createDefaultContext());

        $this->assertSame(3, $this->lineItemFactory->getCreatedFrom()[0]['quantity']);
    }

    /**
     * Without the marker the payment method availability rule drops Mollie during the renewal, so
     * the renewal cart has to look like the storefront "Subscribe" flow.
     */
    public function testTheRenewalLineItemKeepsTheSubscriptionMarker(): void
    {
        $order = $this->order([$this->subscriptionLineItem('line-1', 'product-1', 1, new Interval(1, IntervalUnit::MONTHS))]);

        $groupCart = $this->builder()->buildGroupCart($order, self::MONTHLY, Context::createDefaultContext());

        $this->assertNotNull($groupCart);
        $cartLineItem = $groupCart->getCart()->getLineItems()->first();
        $this->assertInstanceOf(LineItem::class, $cartLineItem);
        $this->assertSame(Mollie::SUBSCRIPTION_LINE_ITEM_PREFIX . 'product-1', $cartLineItem->getId());
        $this->assertTrue($cartLineItem->getPayloadValue(Mollie::SUBSCRIPTION_PAYLOAD_KEY));
    }

    /**
     * The renewal cart must not overwrite the customer's live cart in the database.
     */
    public function testTheRenewalCartIsNotPersisted(): void
    {
        $order = $this->order([$this->subscriptionLineItem('line-1', 'product-1', 1, new Interval(1, IntervalUnit::MONTHS))]);

        $this->builder()->buildGroupCart($order, self::MONTHLY, Context::createDefaultContext());

        $permissions = $this->orderConverter->getLastOverrideOptions()[SalesChannelContextService::PERMISSIONS];
        $this->assertTrue($permissions['skipCartPersistence']);
    }

    /**
     * A customer who changed their address before the renewal must be charged and delivered at the
     * new one, not the one on the original order.
     */
    public function testTheRenewalUsesTheAddressesItWasGiven(): void
    {
        $order = $this->order([$this->subscriptionLineItem('line-1', 'product-1', 1, new Interval(1, IntervalUnit::MONTHS))]);

        $this->builder()->buildGroupCart(
            $order,
            self::MONTHLY,
            Context::createDefaultContext(),
            new RenewalAddresses('billing-address-1', 'shipping-address-1')
        );

        $options = $this->orderConverter->getLastOverrideOptions();
        $this->assertSame('billing-address-1', $options[SalesChannelContextService::BILLING_ADDRESS_ID]);
        $this->assertSame('shipping-address-1', $options[SalesChannelContextService::SHIPPING_ADDRESS_ID]);
    }

    public function testWithoutAddressesTheOrdersOwnAddressesStay(): void
    {
        $order = $this->order([$this->subscriptionLineItem('line-1', 'product-1', 1, new Interval(1, IntervalUnit::MONTHS))]);

        $this->builder()->buildGroupCart($order, self::MONTHLY, Context::createDefaultContext());

        $options = $this->orderConverter->getLastOverrideOptions();
        $this->assertArrayNotHasKey(SalesChannelContextService::BILLING_ADDRESS_ID, $options);
        $this->assertArrayNotHasKey(SalesChannelContextService::SHIPPING_ADDRESS_ID, $options);
    }

    public function testAnOrderWhoseLineItemsWereNotLoadedBuildsNoCart(): void
    {
        $order = new OrderEntity();
        $order->setId('order-1');

        $this->assertNull($this->builder()->buildGroupCart($order, self::MONTHLY, Context::createDefaultContext()));
    }

    public function testAnIntervalTheOrderHasNoProductsForBuildsNoCart(): void
    {
        $order = $this->order([$this->subscriptionLineItem('line-1', 'product-1', 1, new Interval(1, IntervalUnit::MONTHS))]);

        $this->assertNull($this->builder()->buildGroupCart($order, '2 weeks', Context::createDefaultContext()));
    }

    /**
     * A line item whose product was deleted has nothing to add to the cart, so there is no renewal
     * to charge for.
     */
    public function testAGroupWhoseProductIsGoneBuildsNoCart(): void
    {
        $lineItem = $this->subscriptionLineItem('line-1', 'product-1', 1, new Interval(1, IntervalUnit::MONTHS));
        $lineItem->setReferencedId(null);

        $this->assertNull($this->builder()->buildGroupCart($this->order([$lineItem]), self::MONTHLY, Context::createDefaultContext()));
    }

    private function builder(): SubscriptionGroupCartBuilder
    {
        return new SubscriptionGroupCartBuilder(
            new LineItemAnalyzer(),
            $this->orderConverter,
            $this->cartService,
            $this->lineItemFactory
        );
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function order(array $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private function subscriptionLineItem(string $id, string $productId, int $quantity, Interval $interval): OrderLineItemEntity
    {
        $extension = new Product();
        $extension->setIsSubscription(true);
        $extension->setInterval($interval);

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setReferencedId($productId);
        $lineItem->setQuantity($quantity);
        $lineItem->addExtension(Mollie::EXTENSION, $extension);

        return $lineItem;
    }
}
