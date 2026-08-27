<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund;

use Mollie\Shopware\Component\Mollie\CreateOrderRefund;
use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\CreateRefund;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Refund\RefundableTotalCalculator;
use Mollie\Shopware\Component\Refund\RefundBuilder;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(RefundBuilder::class)]
final class RefundBuilderTest extends TestCase
{
    /**
     * Orders paid with an older plugin version never got an "order_line_id" custom field on the
     * delivery, so the shipping line has to take its Mollie line id from the live Mollie order.
     * Without it Mollie rejects the whole refund with "Line 1 contains invalid data. The 'id' field
     * is missing" and nothing at all is refunded.
     */
    public function testDeliveryWithoutCustomFieldTakesTheMollieLineIdFromTheOrder(): void
    {
        $order = $this->buildNetOrder();

        $lineItems = $order->getLineItems();
        self::assertInstanceOf(OrderLineItemCollection::class, $lineItems);
        $lineItem = $lineItems->get('line-1');
        self::assertInstanceOf(OrderLineItemEntity::class, $lineItem);
        // the old plugin version persisted the mollie line id for products...
        $lineItem->setCustomFields(['mollie_payments' => ['order_line_id' => 'odl_product_1']]);
        // ...but never for the delivery, so its custom fields stay empty

        $mollieProductLine = new LineItem('Line line-1', 2, new Money(25.10, 'EUR'), new Money(50.20, 'EUR'));
        $mollieProductLine->setId('odl_product_1');
        $mollieProductLine->setShopwareLineItemId('line-1');
        $mollieProductLine->setRefundableQuantity(2);

        $mollieShippingLine = new LineItem('Mollie Test Shipment', 1, new Money(4.19, 'EUR'), new Money(4.19, 'EUR'));
        $mollieShippingLine->setId('odl_shipping_1');
        $mollieShippingLine->setShopwareLineItemId('delivery-1');
        $mollieShippingLine->setRefundableQuantity(1);

        $gateway = new FakeGateway('', new Payment('tr_test'));
        $gateway->withOrder(new Order('ord_test', '', null, [$mollieProductLine, $mollieShippingLine]));

        $transactionPayment = new Payment('tr_test');
        $transactionPayment->setOrderId('ord_test');

        $builder = new RefundBuilder($gateway, new RefundableTotalCalculator(), new FakeLogger());

        $createRefund = $builder->build($transactionPayment, $order, [
            ['id' => 'line-1', 'quantity' => 2, 'amount' => 50.20, 'resetStock' => 0],
            ['id' => 'delivery-1', 'quantity' => 1, 'amount' => 4.19, 'resetStock' => 0],
        ], '');

        self::assertInstanceOf(CreateOrderRefund::class, $createRefund);

        $payload = $createRefund->toArray();

        self::assertSame([
            ['id' => 'odl_product_1', 'quantity' => 2],
            ['id' => 'odl_shipping_1', 'quantity' => 1],
        ], $payload['lines']);
    }

    /**
     * Klarna captured only a part of the authorization (the rest was released), so Mollie
     * rejects anything above the captured amount with "The specified amount cannot be refunded".
     */
    public function testFullRefundIsCappedToTheAmountMollieStillAccepts(): void
    {
        $molliePayment = new Payment('tr_test');
        $molliePayment->setAmountRemaining(new Money(54.62, 'EUR'));
        $molliePayment->setCapturedAmount(new Money(54.62, 'EUR'));

        $createRefund = $this->buildRefund($molliePayment, null);

        $this->assertSame(54.62, $createRefund->getAmount()->getValue());
    }

    /**
     * Without capture information the whole gross total stays refundable - a net order must not
     * be capped to its net total, since the Mollie payment was created with the gross amount.
     */
    public function testFullRefundOfNetOrderUsesTheGrossTotal(): void
    {
        $molliePayment = new Payment('tr_test');

        $createRefund = $this->buildRefund($molliePayment, null);

        $this->assertSame(84.49, $createRefund->getAmount()->getValue());
    }

    public function testRequestedAmountIsCappedToTheAmountMollieStillAccepts(): void
    {
        $molliePayment = new Payment('tr_test');
        $molliePayment->setAmountRemaining(new Money(54.62, 'EUR'));

        $createRefund = $this->buildRefund($molliePayment, 80.0);

        $this->assertSame(54.62, $createRefund->getAmount()->getValue());
    }

    public function testRequestedAmountBelowTheCapIsKept(): void
    {
        $molliePayment = new Payment('tr_test');
        $molliePayment->setAmountRemaining(new Money(54.62, 'EUR'));

        $createRefund = $this->buildRefund($molliePayment, 10.0);

        $this->assertSame(10.0, $createRefund->getAmount()->getValue());
    }

    public function testAnOrderWithoutALoadedCurrencyIsRejected(): void
    {
        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('10047');
        $order->setSalesChannelId('sales-channel-id');

        $builder = new RefundBuilder(new FakeGateway('', new Payment('tr_test')), new RefundableTotalCalculator(), new FakeLogger());

        $this->expectException(\RuntimeException::class);

        $builder->build(new Payment('tr_test'), $order, [], '');
    }

    public function testAnOrdersApiRefundIsBuiltFromTheRefundableMollieLines(): void
    {
        // The Orders API refunds by line, so only what Mollie still reports as refundable may be sent.
        $mollieOrder = $this->buildOrder([
            'line-1' => ['id' => 'odl_1', 'refundableQuantity' => 2],
            'line-2' => ['id' => 'odl_2', 'refundableQuantity' => 0],
            'delivery-1' => ['id' => 'odl_delivery', 'refundableQuantity' => 1],
        ]);

        $createRefund = $this->buildOrderRefund($mollieOrder);

        $this->assertInstanceOf(CreateOrderRefund::class, $createRefund);
        $this->assertSame(['odl_1', 'odl_delivery'], array_column($createRefund->toArray()['lines'], 'id'));
    }

    public function testOnlyTheStillRefundableQuantityOfALineIsSent(): void
    {
        // One of the two items was already refunded, so only the remaining one may be sent again.
        $mollieOrder = $this->buildOrder([
            'line-1' => ['id' => 'odl_1', 'refundableQuantity' => 1],
        ]);

        $createRefund = $this->buildOrderRefund($mollieOrder);

        $this->assertInstanceOf(CreateOrderRefund::class, $createRefund);
        $this->assertSame([['id' => 'odl_1', 'quantity' => 1]], $createRefund->toArray()['lines']);
    }

    public function testALineMollieDoesNotKnowIsSkippedAndLogged(): void
    {
        // Older orders have no Mollie line id on the delivery; without the id Mollie rejects the whole
        // refund, so the line is dropped and the merchant sees why in the log.
        $logger = new FakeLogger();
        $mollieOrder = $this->buildOrder([
            'line-1' => ['id' => 'odl_1', 'refundableQuantity' => 2],
            'delivery-1' => ['id' => '', 'refundableQuantity' => 1],
        ]);

        $createRefund = $this->buildOrderRefund($mollieOrder, $logger);

        $this->assertInstanceOf(CreateOrderRefund::class, $createRefund);
        $this->assertSame(['odl_1'], array_column($createRefund->toArray()['lines'], 'id'));
        $this->assertTrue($logger->hasRecordThatContains('warning', 'Refund lines without a Mollie line id are skipped'));
    }

    public function testAnOrdersApiRefundWithAnExplicitAmountFallsBackToAPaymentRefund(): void
    {
        // Mollie only honours an amount on a payment refund, so a corrected amount cannot be sent
        // as a line-based order refund.
        $mollieOrder = $this->buildOrder([
            'line-1' => ['id' => 'odl_1', 'refundableQuantity' => 2],
        ]);

        $createRefund = $this->buildOrderRefund($mollieOrder, requestAmount: 10.0);

        $this->assertInstanceOf(CreatePaymentRefund::class, $createRefund);
        $this->assertSame('10.00', $createRefund->toArray()['amount']['value']);
    }

    /**
     * A return of an order with a 25 % discount. The discount sits in its own Mollie line that a
     * return never contains, so refunding the returned positions by line refunded the undiscounted
     * 69.98 EUR instead of the 52.49 EUR the return asks for.
     */
    public function testAReturnOfADiscountedOrderRefundsTheAmountTheReturnAsksFor(): void
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $promotion = $this->buildLineItem('promo-1', -17.49, 1, 19.0, -2.79);
        $promotion->setType('promotion');

        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('M10424635');
        $order->setSalesChannelId('sales-channel-id');
        $order->setCurrency($currency);
        $order->setAmountTotal(52.49);
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setLineItems(new OrderLineItemCollection([
            $this->buildLineItem('line-1', 29.99, 1, 19.0, 4.79),
            $this->buildLineItem('line-2', 39.99, 1, 19.0, 6.38),
            $promotion,
        ]));

        $mollieLines = [];
        foreach (['line-1' => 29.99, 'line-2' => 39.99] as $shopwareId => $price) {
            $mollieLine = new LineItem('Line ' . $shopwareId, 1, new Money($price, 'EUR'), new Money($price, 'EUR'));
            $mollieLine->setId('odl_' . $shopwareId);
            $mollieLine->setShopwareLineItemId($shopwareId);
            $mollieLine->setRefundableQuantity(1);
            $mollieLines[] = $mollieLine;
        }

        $payment = new Payment('tr_test');
        $payment->setOrderId('ord_test');

        $gateway = new FakeGateway('', $payment);
        $gateway->withOrder(new Order('ord_test', '', null, $mollieLines));

        $builder = new RefundBuilder($gateway, new RefundableTotalCalculator(), new FakeLogger());

        // the request the OrderReturnHandler builds: the positions plus the total of the return
        $createRefund = $builder->build($payment, $order, [
            ['id' => 'line-1', 'quantity' => 1, 'amount' => 22.4925, 'resetStock' => 1],
            ['id' => 'line-2', 'quantity' => 1, 'amount' => 29.9925, 'resetStock' => 1],
        ], '', 52.49);

        $this->assertInstanceOf(CreatePaymentRefund::class, $createRefund);
        $this->assertSame('52.49', $createRefund->toArray()['amount']['value']);
    }

    public function testRefundingTheShippingCostsAloneUsesTheDeliveryAmount(): void
    {
        $builder = new RefundBuilder(new FakeGateway('', new Payment('tr_test')), new RefundableTotalCalculator(), new FakeLogger());

        $createRefund = $builder->build(
            new Payment('tr_test'),
            $this->buildNetOrder(),
            [['id' => 'delivery-1', 'quantity' => 1, 'amount' => 0.0, 'resetStock' => 0]],
            ''
        );

        $this->assertInstanceOf(CreatePaymentRefund::class, $createRefund);
        $this->assertSame('4.19', $createRefund->toArray()['amount']['value']);
    }

    public function testAnUnknownRequestedItemIsRejected(): void
    {
        $builder = new RefundBuilder(new FakeGateway('', new Payment('tr_test')), new RefundableTotalCalculator(), new FakeLogger());

        $this->expectException(\RuntimeException::class);

        $builder->build(
            new Payment('tr_test'),
            $this->buildNetOrder(),
            [['id' => 'unknown-line', 'quantity' => 1, 'amount' => 1.0, 'resetStock' => 0]],
            ''
        );
    }

    /**
     * @param array<string, array{id: string, refundableQuantity: int}> $lines keyed by Shopware line id
     */
    private function buildOrder(array $lines): Order
    {
        $mollieLines = [];
        foreach ($lines as $shopwareLineId => $line) {
            $mollieLine = new LineItem('Line ' . $shopwareLineId, 1, new Money(1.0, 'EUR'), new Money(1.0, 'EUR'));
            $mollieLine->setId($line['id']);
            $mollieLine->setShopwareLineItemId($shopwareLineId);
            $mollieLine->setRefundableQuantity($line['refundableQuantity']);
            $mollieLines[] = $mollieLine;
        }

        return new Order('ord_test', '', null, $mollieLines);
    }

    private function buildOrderRefund(Order $mollieOrder, ?FakeLogger $logger = null, ?float $requestAmount = null): CreateRefund
    {
        $payment = new Payment('tr_test');
        $payment->setOrderId('ord_test');

        $gateway = new FakeGateway('', $payment);
        $gateway->withOrder($mollieOrder);

        $builder = new RefundBuilder($gateway, new RefundableTotalCalculator(), $logger ?? new FakeLogger());

        return $builder->build($payment, $this->buildNetOrder(), [], '', $requestAmount);
    }

    private function buildRefund(Payment $molliePayment, ?float $requestAmount): CreatePaymentRefund
    {
        $gateway = new FakeGateway('', $molliePayment);
        $calculator = new RefundableTotalCalculator();
        $logger = new FakeLogger();
        $builder = new RefundBuilder($gateway, $calculator, $logger);

        $transactionPayment = new Payment('tr_test');

        $createRefund = $builder->build($transactionPayment, $this->buildNetOrder(), [], '', $requestAmount);

        $this->assertInstanceOf(CreatePaymentRefund::class, $createRefund);

        return $createRefund;
    }

    /**
     * A net order of 84.49 EUR gross (72.99 EUR net + 11.50 EUR tax).
     */
    private function buildNetOrder(): OrderEntity
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('10047');
        $order->setSalesChannelId('sales-channel-id');
        $order->setCurrency($currency);
        $order->setAmountTotal(84.49);
        $order->setTaxStatus(CartPrice::TAX_STATE_NET);
        $order->setLineItems(new OrderLineItemCollection([
            $this->buildLineItem('line-1', 25.10, 2, 19.0, 9.54),
            $this->buildLineItem('line-2', 18.60, 1, 7.0, 1.30),
        ]));
        $order->setDeliveries(new OrderDeliveryCollection([
            $this->buildDelivery('delivery-1', 4.19, 15.75, 0.66),
        ]));

        return $order;
    }

    private function buildLineItem(string $id, float $unitPrice, int $quantity, float $taxRate, float $taxAmount): OrderLineItemEntity
    {
        $totalPrice = $unitPrice * $quantity;

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setLabel('Line ' . $id);
        $lineItem->setType('product');
        $lineItem->setQuantity($quantity);
        $lineItem->setUnitPrice($unitPrice);
        $lineItem->setTotalPrice($totalPrice);
        $lineItem->setPrice($this->buildPrice($unitPrice, $totalPrice, $quantity, $taxRate, $taxAmount));

        return $lineItem;
    }

    private function buildDelivery(string $id, float $totalPrice, float $taxRate, float $taxAmount): OrderDeliveryEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('shipping-method-id');
        $shippingMethod->setName('Mollie Test Shipment');

        $delivery = new OrderDeliveryEntity();
        $delivery->setId($id);
        $delivery->setShippingMethod($shippingMethod);
        $delivery->setShippingCosts($this->buildPrice($totalPrice, $totalPrice, 1, $taxRate, $taxAmount));

        return $delivery;
    }

    private function buildPrice(float $unitPrice, float $totalPrice, int $quantity, float $taxRate, float $taxAmount): CalculatedPrice
    {
        $calculatedTax = new CalculatedTax($taxAmount, $taxRate, $totalPrice);

        return new CalculatedPrice($unitPrice, $totalPrice, new CalculatedTaxCollection([$calculatedTax]), new TaxRuleCollection(), $quantity);
    }
}
