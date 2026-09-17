<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Cart;

use Mollie\Shopware\Component\Subscription\Cart\SubscriptionCartCollector;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionLineItemAddedEvent;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\EventSpy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[CoversClass(SubscriptionCartCollector::class)]
final class SubscriptionCartCollectorTest extends TestCase
{
    private const SUBSCRIPTION_CUSTOM_FIELDS = [
        'mollie_payments_product_subscription_enabled' => true,
        'mollie_payments_product_subscription_interval' => 1,
        'mollie_payments_product_subscription_interval_unit' => 'months',
    ];

    public function testDispatchesEventOncePerSubscriptionLineItem(): void
    {
        $subscriptionItem = $this->buildLineItem(true, 'sub-1');
        $secondSubscription = $this->buildLineItem(true, 'sub-2');
        $regular = $this->buildLineItem(false, 'reg-1');

        $eventSpy = new EventSpy();
        $collector = new SubscriptionCartCollector($eventSpy);

        $cart = new Cart('cart-token');
        $cart->setLineItems(new LineItemCollection([$subscriptionItem, $secondSubscription, $regular]));

        $collector->collect(
            new CartDataCollection(),
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        $this->assertSame(2, $eventSpy->getEventCount());
        $this->assertInstanceOf(SubscriptionLineItemAddedEvent::class, $eventSpy->getEvents()[0]);
        $this->assertSame($subscriptionItem, $eventSpy->getEvents()[0]->getLineItem());
        $this->assertSame($secondSubscription, $eventSpy->getEvents()[1]->getLineItem());
    }

    public function testBuildsExtensionFromPayloadWhenCartWasNeverLoaded(): void
    {
        $lineItem = new LineItem('renewal-1', 'product');
        $lineItem->setPayloadValue('customFields', self::SUBSCRIPTION_CUSTOM_FIELDS);
        $lineItem->setPayloadValue(Mollie::SUBSCRIPTION_PAYLOAD_KEY, true);

        $eventSpy = new EventSpy();
        $collector = new SubscriptionCartCollector($eventSpy);

        $cart = new Cart('cart-token');
        $cart->setLineItems(new LineItemCollection([$lineItem]));

        $collector->collect(
            new CartDataCollection(),
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        $extension = $lineItem->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Product::class, $extension);
        $this->assertTrue($extension->isSubscription());
        $this->assertSame(1, $eventSpy->getEventCount());
    }

    public function testKeepsStandaloneProductUnmarkedWithoutSubscriptionPayload(): void
    {
        $lineItem = new LineItem('standalone-1', 'product');
        $lineItem->setPayloadValue('customFields', array_merge(
            self::SUBSCRIPTION_CUSTOM_FIELDS,
            ['mollie_payments_product_subscription_allow_onetime' => true]
        ));

        $eventSpy = new EventSpy();
        $collector = new SubscriptionCartCollector($eventSpy);

        $cart = new Cart('cart-token');
        $cart->setLineItems(new LineItemCollection([$lineItem]));

        $collector->collect(
            new CartDataCollection(),
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        $extension = $lineItem->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Product::class, $extension);
        $this->assertFalse($extension->isSubscription());
        $this->assertSame(0, $eventSpy->getEventCount());
    }

    public function testDoesNotOverwriteExistingExtension(): void
    {
        $lineItem = $this->buildLineItem(true, 'existing-1');
        $existing = $lineItem->getExtension(Mollie::EXTENSION);
        $lineItem->setPayloadValue('customFields', self::SUBSCRIPTION_CUSTOM_FIELDS);

        $collector = new SubscriptionCartCollector(new EventSpy());

        $cart = new Cart('cart-token');
        $cart->setLineItems(new LineItemCollection([$lineItem]));

        $collector->collect(
            new CartDataCollection(),
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        $this->assertSame($existing, $lineItem->getExtension(Mollie::EXTENSION));
    }

    public function testDoesNothingForCartWithoutSubscriptionLineItems(): void
    {
        $eventSpy = new EventSpy();
        $collector = new SubscriptionCartCollector($eventSpy);

        $cart = new Cart('cart-token');
        $cart->setLineItems(new LineItemCollection([
            $this->buildLineItem(false, 'reg-1'),
            new LineItem('no-extension', 'product'),
        ]));

        $collector->collect(
            new CartDataCollection(),
            $cart,
            $this->createMock(SalesChannelContext::class),
            new CartBehavior()
        );

        $this->assertSame(0, $eventSpy->getEventCount());
    }

    private function buildLineItem(bool $isSubscription, string $id): LineItem
    {
        $extension = new Product();
        $extension->setIsSubscription($isSubscription);

        $lineItem = new LineItem($id, 'product');
        $lineItem->addExtension(Mollie::EXTENSION, $extension);

        return $lineItem;
    }
}
