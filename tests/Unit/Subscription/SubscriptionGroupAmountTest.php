<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Subscription\SubscriptionGroupAmount;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCart;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderEntity;

#[CoversClass(SubscriptionGroupAmount::class)]
final class SubscriptionGroupAmountTest extends TestCase
{
    public function testFromGroupCartReadsCartPriceTotalsAndTaxStatus(): void
    {
        $cart = new Cart('test-token');
        $cart->setPrice(new CartPrice(
            42.02,
            50.00,
            50.00,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        $amount = SubscriptionGroupAmount::fromGroupCart(new SubscriptionGroupCart($cart, new FakeSalesChannelContext()));

        $this->assertSame(50.00, $amount->gross());
        $this->assertSame(42.02, $amount->net());
        $this->assertSame(CartPrice::TAX_STATE_GROSS, $amount->getTaxStatus());
    }

    public function testFromOrderReadsOrderTotalsAndTaxStatus(): void
    {
        $order = new OrderEntity();
        $order->setAmountTotal(120.00);
        $order->setAmountNet(100.00);
        $order->setTaxStatus(CartPrice::TAX_STATE_NET);

        $amount = SubscriptionGroupAmount::fromOrder($order);

        $this->assertSame(120.00, $amount->gross());
        $this->assertSame(100.00, $amount->net());
        $this->assertSame(CartPrice::TAX_STATE_NET, $amount->getTaxStatus());
    }

    public function testFromGroupCartOrOrderPrefersGroupCartWhenPresent(): void
    {
        $cart = new Cart('test-token');
        $cart->setPrice(new CartPrice(
            8.40,
            10.00,
            10.00,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        $order = new OrderEntity();
        $order->setAmountTotal(999.99);
        $order->setAmountNet(800.00);
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);

        $amount = SubscriptionGroupAmount::fromGroupCartOrOrder(
            new SubscriptionGroupCart($cart, new FakeSalesChannelContext()),
            $order
        );

        $this->assertSame(10.00, $amount->gross());
        $this->assertSame(8.40, $amount->net());
    }

    public function testFromGroupCartOrOrderFallsBackToOrderWhenGroupCartIsNull(): void
    {
        $order = new OrderEntity();
        $order->setAmountTotal(999.99);
        $order->setAmountNet(840.33);
        $order->setTaxStatus(CartPrice::TAX_STATE_FREE);

        $amount = SubscriptionGroupAmount::fromGroupCartOrOrder(null, $order);

        $this->assertSame(999.99, $amount->gross());
        $this->assertSame(840.33, $amount->net());
        $this->assertSame(CartPrice::TAX_STATE_FREE, $amount->getTaxStatus());
    }
}
