<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Subscriber\LineItemSubscriber;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CartLoadedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;

#[CoversClass(LineItemSubscriber::class)]
final class LineItemSubscriberTest extends TestCase
{
    private const SUBSCRIPTION_CUSTOM_FIELDS = [
        'mollie_payments_product_subscription_enabled' => true,
        'mollie_payments_product_subscription_interval' => 1,
        'mollie_payments_product_subscription_interval_unit' => 'months',
    ];

    private LineItemSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new LineItemSubscriber();
    }

    public function testSubscribedEvents(): void
    {
        $events = LineItemSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(CartLoadedEvent::class, $events);
        self::assertArrayHasKey(OrderEvents::ORDER_LINE_ITEM_LOADED_EVENT, $events);
    }

    public function testACartLineItemLearnsItsSubscriptionSettingsFromTheProductCustomFields(): void
    {
        $lineItem = $this->cartLineItem(['customFields' => self::SUBSCRIPTION_CUSTOM_FIELDS]);

        $this->subscriber->onCartLoaded($this->cartLoadedEvent($lineItem));

        $extension = $lineItem->getExtension(Mollie::EXTENSION);
        self::assertInstanceOf(Product::class, $extension);
        self::assertTrue($extension->isSubscription());
        self::assertSame('1 month', (string) $extension->getInterval());
    }

    public function testACartLineItemWithoutProductCustomFieldsIsLeftAlone(): void
    {
        $lineItem = $this->cartLineItem([]);

        $this->subscriber->onCartLoaded($this->cartLoadedEvent($lineItem));

        self::assertFalse($lineItem->hasExtension(Mollie::EXTENSION));
    }

    public function testAnAlreadyDecoratedCartLineItemIsNotDecoratedTwice(): void
    {
        $existing = new Product();
        $lineItem = $this->cartLineItem(['customFields' => self::SUBSCRIPTION_CUSTOM_FIELDS]);
        $lineItem->addExtension(Mollie::EXTENSION, $existing);

        $this->subscriber->onCartLoaded($this->cartLoadedEvent($lineItem));

        self::assertSame($existing, $lineItem->getExtension(Mollie::EXTENSION));
    }

    public function testAProductThatCanAlsoBeBoughtOnceFollowsTheSubscribeButton(): void
    {
        // The storefront sets the marker when the shopper picks the subscription variant; without it
        // the same product is a one-off purchase.
        $customFields = self::SUBSCRIPTION_CUSTOM_FIELDS + ['mollie_payments_product_subscription_allow_onetime' => true];

        $withoutMarker = $this->cartLineItem(['customFields' => $customFields], 'one-off-line-item-id');
        $withMarker = $this->cartLineItem(['customFields' => $customFields, Mollie::SUBSCRIPTION_PAYLOAD_KEY => true], 'subscribed-line-item-id');

        $this->subscriber->onCartLoaded($this->cartLoadedEvent($withoutMarker, $withMarker));

        self::assertFalse($this->extensionOf($withoutMarker)->isSubscription());
        self::assertTrue($this->extensionOf($withMarker)->isSubscription());
    }

    public function testASubscriptionOnlyProductStaysASubscriptionWithoutTheMarker(): void
    {
        $lineItem = $this->cartLineItem(['customFields' => self::SUBSCRIPTION_CUSTOM_FIELDS]);

        $this->subscriber->onCartLoaded($this->cartLoadedEvent($lineItem));

        self::assertTrue($this->extensionOf($lineItem)->isSubscription());
    }

    public function testAnOrderLineItemLearnsItsSubscriptionSettingsFromThePayload(): void
    {
        $lineItem = $this->orderLineItem(['customFields' => self::SUBSCRIPTION_CUSTOM_FIELDS]);

        $this->subscriber->onOrderLineItemLoaded($this->orderLineItemLoadedEvent($lineItem));

        $extension = $lineItem->getExtension(Mollie::EXTENSION);
        self::assertInstanceOf(Product::class, $extension);
        self::assertTrue($extension->isSubscription());
    }

    public function testAnOrderLineItemWithoutProductCustomFieldsIsLeftAlone(): void
    {
        $lineItem = $this->orderLineItem([]);

        $this->subscriber->onOrderLineItemLoaded($this->orderLineItemLoadedEvent($lineItem));

        self::assertFalse($lineItem->hasExtension(Mollie::EXTENSION));
    }

    public function testAnAlreadyDecoratedOrderLineItemIsNotDecoratedTwice(): void
    {
        $existing = new Product();
        $lineItem = $this->orderLineItem(['customFields' => self::SUBSCRIPTION_CUSTOM_FIELDS]);
        $lineItem->addExtension(Mollie::EXTENSION, $existing);

        $this->subscriber->onOrderLineItemLoaded($this->orderLineItemLoadedEvent($lineItem));

        self::assertSame($existing, $lineItem->getExtension(Mollie::EXTENSION));
    }

    public function testAnOrderedProductThatCanAlsoBeBoughtOnceKeepsTheDecisionOfTheOrder(): void
    {
        $customFields = self::SUBSCRIPTION_CUSTOM_FIELDS + ['mollie_payments_product_subscription_allow_onetime' => true];

        $oneOff = $this->orderLineItem(['customFields' => $customFields], 'one-off-line-item-id');
        $subscribed = $this->orderLineItem(['customFields' => $customFields, Mollie::SUBSCRIPTION_PAYLOAD_KEY => true], 'subscribed-line-item-id');

        $this->subscriber->onOrderLineItemLoaded($this->orderLineItemLoadedEvent($oneOff, $subscribed));

        self::assertFalse($this->extensionOf($oneOff)->isSubscription());
        self::assertTrue($this->extensionOf($subscribed)->isSubscription());
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function cartLineItem(array $payload, string $id = 'line-item-id'): LineItem
    {
        $lineItem = new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, 'product-id', 1);
        $lineItem->setPayload($payload);

        return $lineItem;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function orderLineItem(array $payload, string $id = 'line-item-id'): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setPayload($payload);

        return $lineItem;
    }

    private function cartLoadedEvent(LineItem ...$lineItems): CartLoadedEvent
    {
        $cart = CartBuilder::create()->withLineItems(array_values($lineItems))->build();

        return new CartLoadedEvent($cart, new FakeSalesChannelContext());
    }

    private function orderLineItemLoadedEvent(OrderLineItemEntity ...$lineItems): EntityLoadedEvent
    {
        return new EntityLoadedEvent(new OrderLineItemDefinition(), array_values($lineItems), Context::createDefaultContext());
    }

    private function extensionOf(LineItem|OrderLineItemEntity $lineItem): Product
    {
        $extension = $lineItem->getExtension(Mollie::EXTENSION);
        self::assertInstanceOf(Product::class, $extension);

        return $extension;
    }
}
