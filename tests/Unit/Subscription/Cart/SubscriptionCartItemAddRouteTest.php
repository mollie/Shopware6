<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Cart;

use Mollie\Shopware\Component\Subscription\Cart\SubscriptionCartItemAddRoute;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Subscription\Fake\FakeCartItemAddRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Symfony\Component\HttpFoundation\Request;

/**
 * The storefront "Subscribe" button posts the product id under its own request key. This decorator
 * marks the matching cart line item, because the payment method availability rule reads that marker
 * - without it Mollie disappears from the checkout of a subscription cart.
 */
#[CoversClass(SubscriptionCartItemAddRoute::class)]
final class SubscriptionCartItemAddRouteTest extends TestCase
{
    private const PRODUCT_ID = 'product-1';

    public function testTheSubscribedProductIsMarkedAsASubscription(): void
    {
        $decorated = new FakeCartItemAddRoute();
        $lineItem = $this->productLineItem(self::PRODUCT_ID);

        $this->add($decorated, [$lineItem], self::PRODUCT_ID);

        $this->assertTrue($lineItem->getPayloadValue(Mollie::SUBSCRIPTION_PAYLOAD_KEY));
    }

    /**
     * The renewal cart builds the same id, so both paths end up with one line item instead of two.
     */
    public function testTheSubscribedProductGetsTheSubscriptionLineItemId(): void
    {
        $decorated = new FakeCartItemAddRoute();
        $lineItem = $this->productLineItem(self::PRODUCT_ID);

        $this->add($decorated, [$lineItem], self::PRODUCT_ID);

        $this->assertSame(Mollie::SUBSCRIPTION_LINE_ITEM_PREFIX . self::PRODUCT_ID, $lineItem->getId());
    }

    /**
     * A cart can hold several products; only the one the button was pressed for becomes a
     * subscription.
     */
    public function testAnotherProductInTheSameRequestIsLeftAlone(): void
    {
        $decorated = new FakeCartItemAddRoute();
        $otherLineItem = $this->productLineItem('product-2');

        $this->add($decorated, [$this->productLineItem(self::PRODUCT_ID), $otherLineItem], self::PRODUCT_ID);

        $this->assertSame('line-item-product-2', $otherLineItem->getId());
        $this->assertNull($otherLineItem->getPayloadValue(Mollie::SUBSCRIPTION_PAYLOAD_KEY));
    }

    /**
     * Promotions and other non-product lines cannot be subscribed to.
     */
    public function testANonProductLineItemIsLeftAlone(): void
    {
        $decorated = new FakeCartItemAddRoute();
        $promotion = new LineItem('line-item-promotion', LineItem::PROMOTION_LINE_ITEM_TYPE, self::PRODUCT_ID);

        $this->add($decorated, [$promotion], self::PRODUCT_ID);

        $this->assertSame('line-item-promotion', $promotion->getId());
    }

    public function testAnOrdinaryAddToCartLeavesTheLineItemAlone(): void
    {
        $decorated = new FakeCartItemAddRoute();
        $lineItem = $this->productLineItem(self::PRODUCT_ID);

        $this->add($decorated, [$lineItem], subscribedProductId: null);

        $this->assertSame('line-item-' . self::PRODUCT_ID, $lineItem->getId());
        $this->assertNull($lineItem->getPayloadValue(Mollie::SUBSCRIPTION_PAYLOAD_KEY));
    }

    public function testTheItemsAreAlwaysHandedOnToShopware(): void
    {
        $decorated = new FakeCartItemAddRoute();
        $lineItem = $this->productLineItem(self::PRODUCT_ID);

        $this->add($decorated, [$lineItem], self::PRODUCT_ID);

        $this->assertSame([$lineItem], $decorated->getAddedItems());
    }

    /**
     * Shopware calls this route with a null item list in some flows; the decorator must not choke
     * on it, it simply has nothing to mark.
     */
    public function testARequestWithoutItemsStillReachesShopware(): void
    {
        $decorated = new FakeCartItemAddRoute();

        $this->add($decorated, null, self::PRODUCT_ID);

        $this->assertTrue($decorated->wasCalled());
    }

    public function testTheDecoratedRouteIsExposed(): void
    {
        $decorated = new FakeCartItemAddRoute();

        $this->assertSame($decorated, (new SubscriptionCartItemAddRoute($decorated))->getDecorated());
    }

    /**
     * @param null|array<LineItem> $items
     */
    private function add(FakeCartItemAddRoute $decorated, ?array $items, ?string $subscribedProductId): void
    {
        $request = new Request();
        if ($subscribedProductId !== null) {
            $request->request->set(Mollie::SUBSCRIBE_REQUEST_KEY, $subscribedProductId);
        }

        (new SubscriptionCartItemAddRoute($decorated))->add(
            $request,
            CartBuilder::create()->build(),
            new FakeSalesChannelContext(),
            $items
        );
    }

    private function productLineItem(string $productId): LineItem
    {
        return new LineItem('line-item-' . $productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId);
    }
}
