<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\LineItemType;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixer;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionLineBuilder;
use Mollie\Shopware\Unit\Builder\CartBuilder;
use Mollie\Shopware\Unit\Builder\LineItemBuilder;
use Mollie\Shopware\Unit\Builder\LineItemFilterBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * POST /v2/sessions validates the transmitted, rounded line values against the amount and rejects
 * a payload that does not add up, so unlike the Orders API payload the rounding correction here is
 * not optional.
 */
#[CoversClass(SessionLineBuilder::class)]
final class SessionLineBuilderTest extends TestCase
{
    public function testEveryPricedCartItemBecomesALine(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem($this->cartLineItem('shirt', 'Shirt', 19.98))
            ->withLineItem($this->cartLineItem('trousers', 'Trousers', 30.00))
            ->withPrice($this->grossCartPrice(49.98))
            ->build()
        ;

        $lines = $this->sessionLineBuilder()->build($cart, new Money(49.98, 'EUR'), new FakeSalesChannelContext());

        $this->assertCount(2, $lines);
        $this->assertSame('Shirt', $lines->first()?->getDescription());
    }

    /**
     * A cart only exposes its top level items; children hang off their parent and have to be
     * flattened, or a bundled product never reaches Mollie.
     */
    public function testAChildOfACartItemBecomesALineOfItsOwn(): void
    {
        $parent = LineItemBuilder::regular('set')
            ->withLabel('Product set')
            ->withPrice($this->price(0.00))
            ->withChild($this->cartLineItem('shirt', 'Shirt', 19.98))
            ->build()
        ;
        $cart = CartBuilder::create()->withLineItem($parent)->withPrice($this->grossCartPrice(19.98))->build();

        $lines = $this->sessionLineBuilder()->build($cart, new Money(19.98, 'EUR'), new FakeSalesChannelContext());

        $this->assertCount(2, $lines);
    }

    public function testAnItemTheFilterRejectsIsLeftOut(): void
    {
        $bundleParent = LineItemBuilder::regular('bundle')
            ->withLabel('Bundle')
            ->withPrice($this->price(19.98))
            ->withPayload(['zeobvProductsInBundle' => ['shirt']])
            ->build()
        ;
        $cart = CartBuilder::create()
            ->withLineItem($bundleParent)
            ->withLineItem($this->cartLineItem('shirt', 'Shirt', 19.98))
            ->withPrice($this->grossCartPrice(19.98))
            ->build()
        ;

        $lines = $this->sessionLineBuilder()->build($cart, new Money(19.98, 'EUR'), new FakeSalesChannelContext());

        $this->assertCount(1, $lines);
        $this->assertSame('Shirt', $lines->first()?->getDescription());
    }

    /**
     * The session carries the shipping costs as shippingOptions instead. Mollie rejects a payload
     * that has both: "A shipping_fee line is not allowed when shippingOptions are provided".
     */
    public function testTheShippingCostsOfTheCartDoNotBecomeALine(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem($this->cartLineItem('shirt', 'Shirt', 19.98))
            ->withPrice($this->grossCartPrice(25.93))
            ->withShippingCosts($this->price(5.95))
            ->build()
        ;

        $lines = $this->sessionLineBuilder()->build($cart, new Money(19.98, 'EUR'), new FakeSalesChannelContext());

        $this->assertCount(1, $lines);
        $this->assertSame(LineItemType::PHYSICAL, $lines->first()?->getType());
    }

    public function testADifferenceBetweenTheLinesAndTheAmountIsCorrected(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem($this->cartLineItem('shirt', 'Shirt', 19.98))
            ->withPrice($this->grossCartPrice(20.00))
            ->build()
        ;

        $lines = $this->sessionLineBuilder()->build($cart, new Money(20.00, 'EUR'), new FakeSalesChannelContext());

        $this->assertCount(2, $lines);
        $this->assertSame(RoundingDifferenceFixer::SKU, $lines->last()?->getSku());
        $this->assertSame(0.02, $lines->last()?->getAmount()->getValue());
    }

    public function testLinesThatAlreadyAddUpGetNoCorrection(): void
    {
        $cart = CartBuilder::create()
            ->withLineItem($this->cartLineItem('shirt', 'Shirt', 19.98))
            ->withPrice($this->grossCartPrice(19.98))
            ->build()
        ;

        $lines = $this->sessionLineBuilder()->build($cart, new Money(19.98, 'EUR'), new FakeSalesChannelContext());

        $this->assertCount(1, $lines);
    }

    /**
     * On the edit order page there is no cart, the lines come from the order instead.
     */
    public function testTheLinesOfAnOrderAreBuiltFromItsLineItems(): void
    {
        $orderBuilder = new OrderEntityBuilder();
        $order = $this->order(new OrderLineItemCollection([
            $orderBuilder->createOrderLineItemWithType('order-line-item-id', CartLineItem::PRODUCT_LINE_ITEM_TYPE, 19.98),
        ]));

        $lines = $this->sessionLineBuilder()->buildFromOrder($order, new Money(19.98, 'EUR'), new FakeSalesChannelContext());

        $this->assertCount(1, $lines);
    }

    public function testTheDeliveryDiscountPlaceholderOfAnOrderIsLeftOut(): void
    {
        $orderBuilder = new OrderEntityBuilder();
        $order = $this->order(new OrderLineItemCollection([
            $orderBuilder->createOrderLineItemWithType('order-line-item-id', CartLineItem::PRODUCT_LINE_ITEM_TYPE, 19.98),
            $orderBuilder->getDeliveryDiscountPromotionLineItem('Shipping discount'),
        ]));

        $lines = $this->sessionLineBuilder()->buildFromOrder($order, new Money(19.98, 'EUR'), new FakeSalesChannelContext());

        $this->assertCount(1, $lines);
    }

    private function sessionLineBuilder(): SessionLineBuilder
    {
        return new SessionLineBuilder(LineItemFilterBuilder::build(), new RoundingDifferenceFixer());
    }

    private function cartLineItem(string $id, string $label, float $totalPrice): CartLineItem
    {
        return LineItemBuilder::regular($id)->withLabel($label)->withPrice($this->price($totalPrice))->build();
    }

    private function price(float $totalPrice): CalculatedPrice
    {
        return new CalculatedPrice($totalPrice, $totalPrice, new CalculatedTaxCollection(), new TaxRuleCollection());
    }

    private function grossCartPrice(float $totalPrice): CartPrice
    {
        return new CartPrice($totalPrice, $totalPrice, $totalPrice, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS);
    }

    private function order(OrderLineItemCollection $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setTaxStatus(CartPrice::TAX_STATE_GROSS);
        $order->setLineItems($lineItems);

        return $order;
    }
}
