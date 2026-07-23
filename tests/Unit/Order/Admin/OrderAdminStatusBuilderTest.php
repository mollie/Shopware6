<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Order\Admin;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Order\Admin\OrderAdminStatusBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

#[CoversClass(OrderAdminStatusBuilder::class)]
final class OrderAdminStatusBuilderTest extends TestCase
{
    private OrderAdminStatusBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new OrderAdminStatusBuilder();
    }

    public function testBuildShippingTotalIsZeroWithoutMollieOrder(): void
    {
        $total = $this->builder->buildShippingTotal(null);

        self::assertSame('0.00', $total->amount);
        self::assertSame(0, $total->quantity);
        self::assertSame(0, $total->shippable);
    }

    public function testCancelStatusForPaymentsApiDerivesFromShopwareLineItems(): void
    {
        $lineItems = new OrderLineItemCollection([$this->shopwareLineItem('li1', 2)]);

        $status = $this->builder->buildCancelStatus('', null, $lineItems, true);

        self::assertArrayHasKey('li1', $status);
        self::assertTrue($status['li1']->isCancelable);
        self::assertSame(2, $status['li1']->cancelableQuantity);
        self::assertSame(0, $status['li1']->quantityCanceled);
    }

    public function testCancelStatusIsNotCancelableWhenShippingNotAllowed(): void
    {
        $lineItems = new OrderLineItemCollection([$this->shopwareLineItem('li1', 2)]);

        $status = $this->builder->buildCancelStatus('', null, $lineItems, false);

        self::assertFalse($status['li1']->isCancelable);
        self::assertSame(0, $status['li1']->cancelableQuantity);
    }

    public function testShippingStatusForPaymentsApiDerivesFromShopwareLineItems(): void
    {
        $lineItems = new OrderLineItemCollection([$this->shopwareLineItem('li1', 3)]);

        $status = $this->builder->buildShippingStatus('', null, $lineItems, true);

        self::assertArrayHasKey('li1', $status);
        self::assertTrue($status['li1']->isShippable);
        self::assertSame(3, $status['li1']->shippableQuantity);
        self::assertSame(0, $status['li1']->quantityShipped);
    }

    public function testShippingStatusForOrdersApiDerivesFromMollieOrderLines(): void
    {
        $line = new LineItem('product', 1, new Money(10.0, 'EUR'), new Money(10.0, 'EUR'));
        $line->setId('mollie-line-1');
        $line->setShopwareLineItemId('sw1');
        $line->setShippableQuantity(2);
        $line->setQuantityShipped(1);
        $mollieOrder = new Order('ord-1', '', null, [$line]);

        $status = $this->builder->buildShippingStatus('ord-1', $mollieOrder, null, true);

        self::assertArrayHasKey('sw1', $status);
        self::assertTrue($status['sw1']->isShippable);
        self::assertSame(2, $status['sw1']->shippableQuantity);
        self::assertSame(1, $status['sw1']->quantityShipped);
        self::assertSame('mollie-line-1', $status['sw1']->mollieId);
    }

    private function shopwareLineItem(string $id, int $quantity): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setQuantity($quantity);
        $lineItem->setCustomFields([]);

        return $lineItem;
    }
}
