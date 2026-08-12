<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund;

use Mollie\Shopware\Component\Mollie\CreatePaymentRefund;
use Mollie\Shopware\Component\Mollie\Money;
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
