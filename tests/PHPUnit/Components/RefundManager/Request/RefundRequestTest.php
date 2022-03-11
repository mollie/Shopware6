<?php

namespace MolliePayments\Tests\Components\RefundManager\Request;

use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;


class RefundRequestTest extends TestCase
{

    /**
     * This test verifies that our property is correctly set.
     *
     * @return void
     */
    public function testOrderNumber()
    {
        $request = new RefundRequest('ord-123', '', 0);

        $this->assertEquals('ord-123', $request->getOrderNumber());
    }

    /**
     * This test verifies that our property is correctly set.
     *
     * @return void
     */
    public function testDescription()
    {
        $request = new RefundRequest('', 'custom refund', 0);

        $this->assertEquals('custom refund', $request->getDescription());
    }

    /**
     * This test verifies that our description has the correct
     * fallback if no description is provided.
     *
     * @return void
     */
    public function testDescriptionFallback()
    {
        $request = new RefundRequest('ORD-123', '', 0);

        $this->assertEquals('Refunded through Shopware. Order number: ORD-123', $request->getDescription());
    }

    /**
     * This test verifies that our property is correctly set.
     *
     * @return void
     */
    public function testAmount()
    {
        $request = new RefundRequest('', '', 4.99);

        $this->assertEquals(4.99, $request->getAmount());
    }


    /**
     * A full refund with items in Mollie is not done if we
     * provide a custom amount and also no items.
     * Then it's just a custom partial refund with amount.
     *
     * @return void
     */
    public function testIsFullRefundWithItems_EmptyItems()
    {
        $request = new RefundRequest('', '', 4.99);

        $this->assertEquals(false, $request->isFullRefundWithItems(new OrderEntity()));
    }

    /**
     * If we provide items, but the amount of a line item does not match the actual unit Price
     * of this line item, then it's also no full refund because the amount is different.
     * @return void
     */
    public function testIsFullRefundWithItems_ItemsAmountDifferent()
    {
        $order = $this->getOrder();

        $request = new RefundRequest('', '', 0);
        $request->addItem(new RefundRequestItem('line-1', 4.99, 1, 0));

        $this->assertEquals(false, $request->isFullRefundWithItems($order));
    }

    /**
     * If we provide line items and the amount matches the actual unit price sum of
     * the item, then we just proceed with a full refund in Mollie including
     * the line item data.
     * @return void
     */
    public function testIsFullRefundWithItems_ItemsAmountNotDifferent()
    {
        $order = $this->getOrder();

        $request = new RefundRequest('', '', 0);
        $request->addItem(new RefundRequestItem('line-1', 19.99, 1, 0));

        $this->assertEquals(true, $request->isFullRefundWithItems($order));
    }

    /**
     * If we dont provide anything, then we just refund the
     * full order based on the amount. So no custom line items will be sent to Mollie.
     * @return void
     */
    public function testIsFullRefundAmountOnly_NoAmountNoItems()
    {
        $request = new RefundRequest('', '', null);

        $this->assertEquals(true, $request->isFullRefundAmountOnly());
    }

    /**
     * If we do not provide an amount, but items, then it's no full refund that
     * would only be based on the amount.
     * @return void
     */
    public function testIsFullRefundAmountOnly_NoAmountWithItems()
    {
        $request = new RefundRequest('', '', null);
        $request->addItem(new RefundRequestItem('line-1', 19.99, 1, 0));

        $this->assertEquals(false, $request->isFullRefundAmountOnly());
    }

    /**
     * If we provide an amount, then this is not a full refund.
     * This would be a partial amount refund without items.
     * @return void
     */
    public function testIsFullRefundAmountOnly_AmountNoItems()
    {
        $request = new RefundRequest('', '', 4);

        $this->assertEquals(false, $request->isFullRefundAmountOnly());
    }

    /**
     * We do not have a full refund only based on the amount
     * if we also provide amount and the items.
     * @return void
     */
    public function testIsFullRefundAmountOnly_AmountWithItems()
    {
        $request = new RefundRequest('', '', 4);
        $request->addItem(new RefundRequestItem('line-1', 19.99, 1, 0));

        $this->assertEquals(false, $request->isFullRefundAmountOnly());
    }

    /**
     * If we do not provide an amount, then we would have a full refund,
     * so this is no partial refund.
     * @return void
     */
    public function testIsPartialRefundAmountOnly_NoAmountNoItems()
    {
        $request = new RefundRequest('', '', null);

        $this->assertEquals(false, $request->isPartialAmountOnly());
    }

    /**
     * If we provide line items then we do not have a partial refund
     * that is only based on the amount.
     * @return void
     */
    public function testIsPartialRefundAmountOnly_AmountWithItems()
    {
        $request = new RefundRequest('', '', 4);
        $request->addItem(new RefundRequestItem('line-1', 19.99, 1, 0));

        $this->assertEquals(false, $request->isPartialAmountOnly());
    }

    /**
     * If we provide a custom amount and no items, then this is
     * valid for a partial refund only based on the amount.
     * @return void
     */
    public function testIsPartialRefundAmountOnly_AmountNoItems()
    {
        $request = new RefundRequest('', '', 5);

        $this->assertEquals(true, $request->isPartialAmountOnly());
    }

    /**
     * If we do not provide any line items, then this is no partial refund
     * that also includes line items that are sent to Mollie.
     * @return void
     */
    public function testIsPartialRefundWithItems_EmptyItems()
    {
        $request = new RefundRequest('', '', 4.99);

        $this->assertEquals(false, $request->isPartialAmountWithItems(new OrderEntity()));
    }

    /**
     * If we provide line items with a custom amount (not the full unit price)
     * then this is legit for sending it as a partial refund to Mollie that includes line items.
     * @return void
     */
    public function testIsPartialRefundWithItems_ItemsAmountDifferent()
    {
        $order = $this->getOrder();

        $request = new RefundRequest('', '', 0);
        $request->addItem(new RefundRequestItem('line-1', 4.99, 1, 0));

        $this->assertEquals(true, $request->isPartialAmountWithItems($order));
    }

    /**
     * If we include line items, but the sum of the line items is equal to the cart sum
     * then this is no refund with a partial amount. This would be a full refund.
     * @return void
     */
    public function testIsPartialRefundWithItems_ItemsAmountNotDifferent()
    {
        $order = $this->getOrder();

        $request = new RefundRequest('', '', 0);
        $request->addItem(new RefundRequestItem('line-1', 19.99, 1, 0));

        $this->assertEquals(false, $request->isPartialAmountWithItems($order));
    }

    /**
     * @return OrderEntity
     */
    private function getOrder(): OrderEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId('line-1');
        $lineItem->setUnitPrice(19.99);

        $order = new OrderEntity();
        $order->setLineItems(new OrderLineItemCollection([$lineItem]));

        return $order;
    }

}
