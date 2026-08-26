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

        self::assertSame('', $item->getRefundId());
        self::assertNull($item->getRefund());
        self::assertSame('', $item->getMollieLineId());
        self::assertSame('', $item->getLabel());
        self::assertSame(0, $item->getQuantity());
        self::assertSame(0.0, $item->getAmount());
        self::assertNull($item->getOrderLineItemId());
        self::assertNull($item->getOrderLineItemVersionId());
        self::assertNull($item->getOrderDeliveryId());
        self::assertNull($item->getOrderLineItem());
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

        self::assertSame('refund-id', $item->getRefundId());
        self::assertSame($refund, $item->getRefund());
        self::assertSame('odl_123', $item->getMollieLineId());
        self::assertSame('Product SW100', $item->getLabel());
        self::assertSame(2, $item->getQuantity());
        self::assertSame(24.98, $item->getAmount());
        self::assertSame('order-line-item-id', $item->getOrderLineItemId());
        self::assertSame('order-version-id', $item->getOrderLineItemVersionId());
        self::assertSame('order-delivery-id', $item->getOrderDeliveryId());
        self::assertSame($orderLineItem, $item->getOrderLineItem());
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

        self::assertSame([
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

        self::assertSame([
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

        self::assertArrayNotHasKey('refundId', $row);
    }

    public function testAnEmptyRefundIdIsTreatedLikeAMissingOne(): void
    {
        $row = RefundItemEntity::createArray('odl_123', 'Product SW100', 2, 24.98, 'order-line-item-id', 'order-version-id', '');

        self::assertArrayNotHasKey('refundId', $row);
    }
}
