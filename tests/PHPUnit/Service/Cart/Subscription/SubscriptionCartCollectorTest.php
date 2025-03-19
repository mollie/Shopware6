<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\Cart\Subscription;

use Kiener\MolliePayments\Event\MollieSubscriptionCartItemAddedEvent;
use Kiener\MolliePayments\Service\Cart\Subscription\SubscriptionCartCollector;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CheckoutCartLineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SubscriptionCartCollectorTest extends TestCase
{
    public const SUBSCRIPTION_ENABLED = 'mollie_payments_product_subscription_enabled';
    private $dispatcher;
    private $collector;
    private $data;

    private $context;
    private $original;
    private $behavior;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->collector = new SubscriptionCartCollector($this->dispatcher);
        $this->data = $this->createMock(CartDataCollection::class);
        $this->original = $this->createMock(Cart::class);
        $this->context = $this->createMock(SalesChannelContext::class);
        $this->behavior = $this->createMock(CartBehavior::class);
    }

    public function testDispatchesEventWhenAProductIsAMollieSubscriptionProduct(): void
    {
        // this will cause the line item to be considered a subscription product and trigger the event
        $subscriptionProduct = $this->createLineItemMockWithPayloadValue([self::SUBSCRIPTION_ENABLED => true]);

        // this will cause the line item to be considered a regular product and not trigger the event
        $regularProduct = $this->createLineItemMockWithPayloadValue([self::SUBSCRIPTION_ENABLED => false]);

        $this->configureGetLineItemsMethodOfCart($subscriptionProduct, $regularProduct);

        // we expect the event to be dispatched only once
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(MollieSubscriptionCartItemAddedEvent::class))
        ;

        $this->collector->collect($this->data, $this->original, $this->context, $this->behavior);
    }

    public function testDoesNotDispatchEventWhenNoMollieSubscriptionProductIsAdded(): void
    {
        // this will cause the line item to be considered a regular product and not trigger the event
        $regularProduct = $this->createLineItemMockWithPayloadValue([self::SUBSCRIPTION_ENABLED => false]);

        $this->configureGetLineItemsMethodOfCart($regularProduct);

        // we expect the event to not be dispatched
        $this->dispatcher->expects($this->never())->method('dispatch');

        $this->collector->collect($this->data, $this->original, $this->context, $this->behavior);
    }

    private function createLineItemMockWithPayloadValue($value): CheckoutCartLineItem
    {
        return (new CheckoutCartLineItem(
            Uuid::randomBytes(),
            'product'
        ))->setPayload(['customFields' => $value]);
    }

    private function configureGetLineItemsMethodOfCart(CheckoutCartLineItem ...$items): void
    {
        $this->original->method('getLineItems')->willReturn(new LineItemCollection($items));
    }
}
