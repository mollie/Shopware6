<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Subscription\SubscriptionLineItemsResolver;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\LineItemBuilder;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Payment\Fake\FakeCartService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * A subscription is either started from the current cart (checkout) or read back from a placed
 * order (the subscription overview). Which of the two applies is decided by the order id alone.
 */
#[CoversClass(SubscriptionLineItemsResolver::class)]
final class SubscriptionLineItemsResolverTest extends TestCase
{
    public function testWithoutAnOrderTheCurrentCartIsUsed(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::regular('product-1')->build())
            ->build()
        ;

        $resolver = new SubscriptionLineItemsResolver(new FakeCartService($cart), new FakeOrderSearchRepository());

        $lineItems = $resolver->resolveLineItems('', new FakeSalesChannelContext());

        $this->assertSame(1, $lineItems->count());
    }

    public function testWithAnOrderItsLineItemsAreUsed(): void
    {
        $orderRepository = new FakeOrderSearchRepository();
        $orderRepository->add($this->order('order-1', new OrderLineItemCollection([$this->orderLineItem()])));

        $resolver = new SubscriptionLineItemsResolver(new FakeCartService(CartBuilder::create()->build()), $orderRepository);

        $lineItems = $resolver->resolveLineItems('order-1', new FakeSalesChannelContext());

        $this->assertSame(1, $lineItems->count());
    }

    /**
     * An order that was deleted between two requests must not fall back to the customer's current
     * cart - that would start a subscription for the wrong articles.
     */
    public function testAnOrderThatIsNotThereResolvesToNoLineItems(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem(LineItemBuilder::regular('product-1')->build())
            ->build()
        ;

        $resolver = new SubscriptionLineItemsResolver(new FakeCartService($cart), new FakeOrderSearchRepository());

        $lineItems = $resolver->resolveLineItems('order-that-is-gone', new FakeSalesChannelContext());

        $this->assertSame(0, $lineItems->count());
    }

    public function testAnOrderWhoseLineItemsWereNotLoadedResolvesToNoLineItems(): void
    {
        $orderRepository = new FakeOrderSearchRepository();
        $orderRepository->add($this->order('order-1', null));

        $resolver = new SubscriptionLineItemsResolver(new FakeCartService(CartBuilder::create()->build()), $orderRepository);

        $lineItems = $resolver->resolveLineItems('order-1', new FakeSalesChannelContext());

        $this->assertSame(0, $lineItems->count());
    }

    private function order(string $id, ?OrderLineItemCollection $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($id);
        if ($lineItems !== null) {
            $order->setLineItems($lineItems);
        }

        return $order;
    }

    private function orderLineItem(): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId('order-line-item-1');
        $lineItem->setLabel('Product A');
        $lineItem->setQuantity(1);

        return $lineItem;
    }
}
