<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\RefundManager\Request;

use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItem;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
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
        $request = new RefundRequest('ord-123', '', '', 0);

        $this->assertEquals('ord-123', $request->getOrderNumber());
    }

    /**
     * This test verifies that our property is correctly set.
     *
     * @return void
     */
    public function testDescription()
    {
        $request = new RefundRequest('', 'custom refund', '', 0);

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
        $request = new RefundRequest('ORD-123', '', '', 0);

        $this->assertEquals('Refunded through Shopware. Order number: ORD-123', $request->getDescription());
    }

    /**
     * This test verifies that our property is correctly set.
     *
     * @return void
     */
    public function testInternalDescription()
    {
        $request = new RefundRequest('', '', 'custom refund', 0);

        $this->assertEquals('custom refund', $request->getInternalDescription());
    }

    /**
     * This test verifies that our property is correctly set.
     *
     * @return void
     */
    public function testAmount()
    {
        $request = new RefundRequest('', '', '', 4.99);

        $this->assertEquals(4.99, $request->getAmount());
    }

    /**
     * A full refund with items in Mollie is not done if we
     * provide a custom amount and also no items.
     * Then it's just a custom partial refund with amount.
     *
     * @return void
     */
    public function testIsFullRefundWithItemsEmptyItems()
    {
        $request = new RefundRequest('', '', '', 4.99);

        $this->assertEquals(false, $request->isFullRefundWithItems(new OrderEntity()));
    }

    /**
     * If we provide items, but the amount of a line item does not match the actual unit Price
     * of this line item, then it's also no full refund because the amount is different.
     *
     * @return void
     */
    public function testIsFullRefundWithItemsItemsAmountDifferent()
    {
        $order = $this->getOrder();

        $request = new RefundRequest('', '', '', 0);
        $request->addItem(new RefundRequestItem('line-1', 4.99, 1, 0));

        $this->assertEquals(false, $request->isFullRefundWithItems($order));
    }

    /**
     * If we provide line items and the amount matches the actual unit price sum of
     * the item, then we just proceed with a full refund in Mollie including
     * the line item data.
     *
     * @return void
     */
    public function testIsFullRefundWithItemsItemsAmountNotDifferent()
    {
        $order = $this->getOrder();

        $request = new RefundRequest('', '', '', 0);
        $request->addItem(new RefundRequestItem('line-1', 19.99, 1, 0));

        $this->assertEquals(true, $request->isFullRefundWithItems($order));
    }

    /**
     * If we have a delivery item with a partial amount, then we do
     * not want to have a full refund with items.
     * We build an order with a delivery item, and then refund that item as quantity 1
     * but with a partial amount only.
     *
     * @return void
     */
    public function testIsNoFullRefundWithItemsPartialAmountDelivery()
    {
        $order = $this->getOrder();

        $delivery = new OrderDeliveryEntity();
        $delivery->setId('delivery-1');
        $delivery->setShippingCosts(
            new CalculatedPrice(
                4.99,
                4.99,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                1,
                null,
                null
            )
        );

        $order->setDeliveries(new OrderDeliveryCollection([$delivery]));

        $request = new RefundRequest('', '', '', 0);
        $request->addItem(new RefundRequestItem('delivery-1', 2, 1, 0));

        $this->assertEquals(false, $request->isFullRefundWithItems($order));
    }

    /**
     * If we dont provide anything, then we just refund the
     * full order based on the amount. So no custom line items will be sent to Mollie.
     *
     * @return void
     */
    public function testIsFullRefundAmountOnlyNoAmountNoItems()
    {
        $request = new RefundRequest('', '', '', null);

        $this->assertEquals(true, $request->isFullRefundAmountOnly());
    }

    /**
     * If we do not provide an amount, but items, then it's no full refund that
     * would only be based on the amount.
     *
     * @return void
     */
    public function testIsFullRefundAmountOnlyNoAmountWithItems()
    {
        $request = new RefundRequest('', '', '', null);
        $request->addItem(new RefundRequestItem('line-1', 19.99, 1, 0));

        $this->assertEquals(false, $request->isFullRefundAmountOnly());
    }

    /**
     * If we provide an amount, then this is not a full refund.
     * This would be a partial amount refund without items.
     *
     * @return void
     */
    public function testIsFullRefundAmountOnlyAmountNoItems()
    {
        $request = new RefundRequest('', '', '', 4);

        $this->assertEquals(false, $request->isFullRefundAmountOnly());
    }

    /**
     * We do not have a full refund only based on the amount
     * if we also provide amount and the items.
     *
     * @return void
     */
    public function testIsFullRefundAmountOnlyAmountWithItems()
    {
        $request = new RefundRequest('', '', '', 4);
        $request->addItem(new RefundRequestItem('line-1', 19.99, 1, 0));

        $this->assertEquals(false, $request->isFullRefundAmountOnly());
    }

    /**
     * If we do not provide an amount, then we would have a full refund,
     * so this is no partial refund.
     *
     * @return void
     */
    public function testIsPartialRefundAmountOnlyNoAmountNoItems()
    {
        $request = new RefundRequest('', '', '', null);

        $this->assertEquals(false, $request->isPartialAmountOnly());
    }

    /**
     * If we provide line items then we do not have a partial refund
     * that is only based on the amount.
     *
     * @return void
     */
    public function testIsPartialRefundAmountOnlyAmountWithItems()
    {
        $request = new RefundRequest('', '', '', 4);
        $request->addItem(new RefundRequestItem('line-1', 19.99, 1, 0));

        $this->assertEquals(false, $request->isPartialAmountOnly());
    }

    /**
     * If we provide a custom amount and no items, then this is
     * valid for a partial refund only based on the amount.
     *
     * @return void
     */
    public function testIsPartialRefundAmountOnlyAmountNoItems()
    {
        $request = new RefundRequest('', '', '', 5);

        $this->assertEquals(true, $request->isPartialAmountOnly());
    }

    /**
     * If we do not provide any line items, then this is no partial refund
     * that also includes line items that are sent to Mollie.
     *
     * @return void
     */
    public function testIsPartialRefundWithItemsEmptyItems()
    {
        $request = new RefundRequest('', '', '', 4.99);

        $this->assertEquals(false, $request->isPartialAmountWithItems(new OrderEntity()));
    }

    /**
     * If we provide line items with a custom amount (not the full unit price)
     * then this is legit for sending it as a partial refund to Mollie that includes line items.
     *
     * @return void
     */
    public function testIsPartialRefundWithItemsItemsAmountDifferent()
    {
        $order = $this->getOrder();

        $request = new RefundRequest('', '', '', 0);
        $request->addItem(new RefundRequestItem('line-1', 4.99, 1, 0));

        $this->assertEquals(true, $request->isPartialAmountWithItems($order));
    }

    /**
     * If we include line items, but the sum of the line items is equal to the cart sum
     * then this is no refund with a partial amount. This would be a full refund.
     *
     * @return void
     */
    public function testIsPartialRefundWithItemsItemsAmountNotDifferent()
    {
        $order = $this->getOrder();

        $request = new RefundRequest('', '', '', 0);
        $request->addItem(new RefundRequestItem('line-1', 19.99, 1, 0));

        $this->assertEquals(false, $request->isPartialAmountWithItems($order));
    }

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
