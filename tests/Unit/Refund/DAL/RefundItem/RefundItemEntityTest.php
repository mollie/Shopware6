<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\DAL\RefundItem;

use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Mollie\Shopware\Component\Refund\DAL\RefundItem\RefundItemEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

#[CoversClass(RefundItemEntity::class)]
final class RefundItemEntityTest extends TestCase
{
    public function testAFreshRefundItemRefersToNothing(): void
    {
        $item = new RefundItemEntity();

        static::assertSame('', $item->getRefundId());
        static::assertNull($item->getRefund());
        static::assertSame('', $item->getMollieLineId());
        static::assertSame('', $item->getLabel());
        static::assertSame(0, $item->getQuantity());
        static::assertSame(0.0, $item->getAmount());
        static::assertNull($item->getOrderLineItemId());
        static::assertNull($item->getOrderLineItemVersionId());
        static::assertNull($item->getOrderDeliveryId());
        static::assertNull($item->getOrderLineItem());
    }

    public function testARefundItemCarriesWhatWasRefunded(): void
    {
        $refund = new RefundEntity();
        $refund->setId('refund-id');

        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId('order-line-item-id');

        $item = new RefundItemEntity();
        $item->setRefundId('refund-id');
        $item->setRefund($refund);
        $item->setMollieLineId('odl_123');
        $item->setLabel('Product SW100');
        $item->setQuantity(2);
        $item->setAmount(24.98);
        $item->setOrderLineItemId('order-line-item-id');
        $item->setOrderLineItemVersionId('order-version-id');
        $item->setOrderDeliveryId('order-delivery-id');
        $item->setOrderLineItem($orderLineItem);

        static::assertSame('refund-id', $item->getRefundId());
        static::assertSame($refund, $item->getRefund());
        static::assertSame('odl_123', $item->getMollieLineId());
        static::assertSame('Product SW100', $item->getLabel());
        static::assertSame(2, $item->getQuantity());
        static::assertSame(24.98, $item->getAmount());
        static::assertSame('order-line-item-id', $item->getOrderLineItemId());
        static::assertSame('order-version-id', $item->getOrderLineItemVersionId());
        static::assertSame('order-delivery-id', $item->getOrderDeliveryId());
        static::assertSame($orderLineItem, $item->getOrderLineItem());
    }

    public function testTheAdministrationSeesTheRefundedLineWithoutTheMollieInternals(): void
    {
        $item = new RefundItemEntity();
        $item->setMollieLineId('odl_123');
        $item->setLabel('Product SW100');
        $item->setQuantity(2);
        $item->setAmount(24.98);
        $item->setOrderLineItemId('order-line-item-id');
        $item->setOrderDeliveryId('order-delivery-id');

        static::assertSame([
            'swReference' => 'Product SW100',
            'label' => 'Product SW100',
            'quantity' => 2,
            'amount' => 24.98,
            'orderLineItemId' => 'order-line-item-id',
            'orderDeliveryId' => 'order-delivery-id',
        ], $item->jsonSerialize());
    }

    public function testTheWritePayloadOfAShippingRefundHasNoLineItem(): void
    {
        // Shipping costs are refunded through the delivery, not through an order line item.
        $row = RefundItemEntity::createArray('odl_delivery', 'Shipping', 1, 4.99, null, null, 'refund-id', 'order-delivery-id');

        static::assertSame([
            'mollieLineId' => 'odl_delivery',
            'label' => 'Shipping',
            'quantity' => 1,
            'amount' => 4.99,
            'orderLineItemId' => null,
            'orderLineItemVersionId' => null,
            'orderDeliveryId' => 'order-delivery-id',
            'refundId' => 'refund-id',
        ], $row);
    }

    public function testTheWritePayloadOmitsTheRefundIdWhenTheRefundIsWrittenInTheSameCall(): void
    {
        // Written as a nested association of the refund, so the refund id does not exist yet.
        $row = RefundItemEntity::createArray('odl_123', 'Product SW100', 2, 24.98, 'order-line-item-id', 'order-version-id');

        static::assertArrayNotHasKey('refundId', $row);
    }

    public function testAnEmptyRefundIdIsTreatedLikeAMissingOne(): void
    {
        $row = RefundItemEntity::createArray('odl_123', 'Product SW100', 2, 24.98, 'order-line-item-id', 'order-version-id', '');

        static::assertArrayNotHasKey('refundId', $row);
    }
}
