<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Exception\MissingLineItemPriceException;
use Mollie\Shopware\Component\Mollie\Exception\MissingShippingMethodException;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\LineItemType;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\CustomerEntityBuilder;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(LineItem::class)]
final class LineItemTest extends TestCase
{
    private OrderEntityBuilder $orderRepository;

    public function setUp(): void
    {
        $this->orderRepository = new OrderEntityBuilder();
    }

    public function testSettersAndGetters(): void
    {
        $price = new Money(10.99, 'EUR');
        $discountAmount = new Money(1.99, 'EUR');
        $vatAmount = new Money(1.99, 'EUR');
        $lineItem = new LineItem('test', 1, $price, $price);
        $lineItem->setQuantityUnit('pc');
        $lineItem->setSku('test');
        $lineItem->setDiscountAmount($discountAmount);
        $lineItem->setVatAmount($vatAmount);
        $lineItem->setVatRate('7');
        $lineItem->setImageUrl('test.jpg');
        $lineItem->setProductUrl('test.com/1234');

        $this->assertSame('test', $lineItem->getDescription());
        $this->assertSame(1, $lineItem->getQuantity());
        $this->assertEquals($price, $lineItem->getUnitPrice());
        $this->assertEquals($price, $lineItem->getAmount());
        $this->assertSame('physical', $lineItem->getType()->value);
        $this->assertSame('pc', $lineItem->getQuantityUnit());
        $this->assertEquals($discountAmount, $lineItem->getDiscountAmount());
        $this->assertEquals($vatAmount, $lineItem->getVatAmount());
        $this->assertSame('7', $lineItem->getVatRate());
        $this->assertSame('test.jpg', $lineItem->getImageUrl());
        $this->assertSame('test.com/1234', $lineItem->getProductUrl());
        $this->assertSame('test', $lineItem->getSku());
    }

    public function testExpectExceptionOnEmptyShippingMethod(): void
    {
        $delivery = new OrderDeliveryEntity();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        $this->expectException(MissingShippingMethodException::class);
        LineItem::fromDelivery($delivery, $currency);
    }

    public function testCanCreateFromDelivery(): void
    {
        $customerRepository = new CustomerEntityBuilder();
        $customer = $customerRepository->getDefaultCustomer();
        $delivery = $this->orderRepository->getOrderDeliveries($customer)->first();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $actual = LineItem::fromDelivery($delivery, $currency);

        $expected = [
            'description' => 'DHL',
            'quantity' => 1,
            'type' => 'shipping_fee',
            'sku' => 'mol-delivery-fake-shipping-method-id',
            'unitPrice' => new Money(4.99, 'EUR'),
            'amount' => new Money(4.99, 'EUR'),
        ];

        $this->assertInstanceOf(LineItem::class, $actual);

        $this->assertSame($expected['description'], $actual->getDescription());
        $this->assertSame($expected['quantity'], $actual->getQuantity());
        $this->assertSame($expected['type'], $actual->getType()->value);
        $this->assertSame($expected['sku'], $actual->getSku());
        $this->assertEquals($expected['unitPrice'], $actual->getUnitPrice());
        $this->assertEquals($expected['amount'], $actual->getAmount());
    }

    public function testCreateFromDeliveryUsesTranslatedShippingMethodName(): void
    {
        $customerRepository = new CustomerEntityBuilder();
        $customer = $customerRepository->getDefaultCustomer();
        $delivery = $this->orderRepository->getOrderDeliveryWithTranslatedShippingMethodName($customer);
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $actual = LineItem::fromDelivery($delivery, $currency);

        $this->assertSame('DHL Translated', $actual->getDescription());
    }

    public function testCreateFromDeliveryFallsBackToShippingWhenNameIsEmpty(): void
    {
        $customerRepository = new CustomerEntityBuilder();
        $customer = $customerRepository->getDefaultCustomer();
        $delivery = $this->orderRepository->getOrderDeliveryWithEmptyShippingMethodName($customer);
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $actual = LineItem::fromDelivery($delivery, $currency);

        $this->assertSame('Shipping', $actual->getDescription());
    }

    public function testExpectExceptionOnEmptyLineItemPrice(): void
    {
        $this->expectException(MissingLineItemPriceException::class);
        $orderLineItem = $this->orderRepository->getOrderLineItemWithoutPrice();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        LineItem::fromOrderLine($orderLineItem, $currency);
    }

    public function testCanCreateFromOrderLine(): void
    {
        $lineItems = $this->orderRepository->getLineItems();
        $orderLineItem = $lineItems->first();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $actual = LineItem::fromOrderLine($orderLineItem, $currency);

        $expected = [
            'description' => 'Fake product',
            'quantity' => 1,
            'type' => 'digital',
            'sku' => 'SW1000',
            'unitPrice' => new Money(10.99, 'EUR'),
            'amount' => new Money(10.99, 'EUR'),
        ];

        $this->assertInstanceOf(LineItem::class, $actual);
        $this->assertSame($expected['description'], $actual->getDescription());
        $this->assertSame($expected['quantity'], $actual->getQuantity());
        $this->assertSame($expected['type'], $actual->getType()->value);
        $this->assertSame($expected['sku'], $actual->getSku());
        $this->assertEquals($expected['unitPrice'], $actual->getUnitPrice());
        $this->assertEquals($expected['amount'], $actual->getAmount());
    }

    public function testCanCreateFromOrderLineWithVoucherCategoriesArray(): void
    {
        $orderLineItem = $this->orderRepository->getLineItemWithVoucherCategory();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $actual = LineItem::fromOrderLine($orderLineItem, $currency);

        $this->assertInstanceOf(LineItem::class, $actual);
        $this->assertSame('Voucher product', $actual->getDescription());
        $this->assertSame('SW1001', $actual->getSku());
        $this->assertCount(2, $actual->getCategories());
    }

    public function testCanCreateFromOrderLineWithSingleVoucherCategory(): void
    {
        $orderLineItem = $this->orderRepository->getLineItemWithSingleVoucherCategory();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $actual = LineItem::fromOrderLine($orderLineItem, $currency);

        $this->assertInstanceOf(LineItem::class, $actual);
        $this->assertSame('Single voucher product', $actual->getDescription());
        $this->assertSame('SW1002', $actual->getSku());
        $this->assertCount(1, $actual->getCategories());
    }

    /**
     * A percentage discount that spans products with different tax rates produces a
     * line item with multiple CalculatedTax entries. Mollie only accepts a single
     * vatRate/vatAmount per line and validates that
     * vatAmount === totalAmount * vatRate / (100 + vatRate).
     *
     * For a net (B2B) customer the values must stay consistent against the gross
     * totalAmount we send, otherwise the API rejects the payment ("vatAmount is off").
     */
    public function testBlendedTaxForNetDiscountIsConsistentWithGrossAmount(): void
    {
        $orderLineItem = $this->orderRepository->getDiscountLineItemWithMultipleTaxesNet();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $actual = LineItem::fromOrderLine($orderLineItem, $currency, CartPrice::TAX_STATE_NET);

        // vatAmount must be the real summed tax (-0.651 + -2.3845), not a back-derived value
        $this->assertEqualsWithDelta(-3.0355, $actual->getVatAmount()->getValue(), 0.0001);

        // gross totalAmount = net (-21.85) + tax (-3.0355)
        $this->assertEqualsWithDelta(-24.8855, $actual->getAmount()->getValue(), 0.0001);

        // average rate derived from the net base: 3.0355 / 21.85 * 100
        $this->assertSame('13.89', $actual->getVatRate());

        // the invariant Mollie enforces, computed on the serialized 2-decimal values
        $payload = $actual->jsonSerialize();
        $vatRate = (float) $payload['vatRate'];
        $totalAmount = (float) $payload['totalAmount']->jsonSerialize()['value'];
        $vatAmount = (float) $payload['vatAmount']->jsonSerialize()['value'];

        $expectedVatAmount = round($totalAmount * $vatRate / (100 + $vatRate), 2);
        $this->assertSame($expectedVatAmount, $vatAmount);
    }

    /**
     * Currencies configured with zero item-rounding decimals (e.g. PLN, SEK, CZK) make
     * Shopware round the line tax to whole numbers: a 678.00 line at 23% stores 127.00
     * instead of 126.78. Mollie validates vatAmount === totalAmount * vatRate / (100 + vatRate)
     * and rejects the payment ("The 'vatAmount' field is off. Expected to be PLN 126.78 ...,
     * got PLN 127.00"). The vatAmount must be derived from the transmitted totalAmount
     * whenever the Shopware value breaks that invariant.
     */
    public function testVatAmountIsDerivedWhenShopwareRoundedTaxBreaksTheMollieInvariant(): void
    {
        $orderLineItem = $this->orderRepository->getLineItemWithWholeNumberRoundedTax();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('PLN');

        $actual = LineItem::fromOrderLine($orderLineItem, $currency);

        $this->assertSame(126.78, $actual->getVatAmount()->getValue());
        $this->assertSame('23', $actual->getVatRate());
        $this->assertSame(678.0, $actual->getAmount()->getValue());

        // the invariant Mollie enforces, computed on the serialized 2-decimal values
        $payload = $actual->jsonSerialize();
        $vatRate = (float) $payload['vatRate'];
        $totalAmount = (float) $payload['totalAmount']->jsonSerialize()['value'];
        $vatAmount = (float) $payload['vatAmount']->jsonSerialize()['value'];

        $this->assertSame(round($totalAmount * $vatRate / (100 + $vatRate), 2), $vatAmount);
    }

    public function testCanCreateFromOrderLineWithMixedVoucherCategories(): void
    {
        $orderLineItem = $this->orderRepository->getLineItemWithMixedVoucherCategories();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        $actual = LineItem::fromOrderLine($orderLineItem, $currency);

        $this->assertInstanceOf(LineItem::class, $actual);
        $this->assertSame('Mixed voucher product', $actual->getDescription());
        $this->assertSame('SW1003', $actual->getSku());

        $this->assertCount(2, $actual->getCategories());
    }

    public function testDeliveryAdoptsTheMollieLineIdFromItsCustomFields(): void
    {
        $customer = (new CustomerEntityBuilder())->getDefaultCustomer();
        $delivery = $this->orderRepository->getOrderDeliveries($customer)->first();
        $delivery->setCustomFields([Mollie::EXTENSION => ['order_line_id' => 'odl_delivery']]);

        $actual = LineItem::fromDelivery($delivery, $this->euro());

        $this->assertSame('odl_delivery', $actual->getId());
    }

    public function testOrderLineAdoptsTheMollieLineIdFromItsCustomFields(): void
    {
        $orderLineItem = $this->orderRepository->getLineItems()->first();
        $orderLineItem->setCustomFields([Mollie::EXTENSION => ['order_line_id' => 'odl_line']]);

        $actual = LineItem::fromOrderLine($orderLineItem, $this->euro());

        $this->assertSame('odl_line', $actual->getId());
    }

    public function testCartLineItemWithoutPriceIsRejected(): void
    {
        $cartLineItem = new CartLineItem('cart-line-1', CartLineItem::PRODUCT_LINE_ITEM_TYPE);
        $cartLineItem->setLabel('Product A');

        $this->expectException(MissingLineItemPriceException::class);
        LineItem::fromCartLineItem($cartLineItem, $this->euro());
    }

    public function testCanCreateFromCartLineItem(): void
    {
        $cartLineItem = new CartLineItem('cart-line-1', CartLineItem::PRODUCT_LINE_ITEM_TYPE);
        $cartLineItem->setLabel('Product A');
        $cartLineItem->setPayload(['productNumber' => 'SW10001']);
        $cartLineItem->setPrice($this->grossPrice(10.00, 20.00, 19.0, 2));

        $actual = LineItem::fromCartLineItem($cartLineItem, $this->euro());

        $this->assertSame('Product A', $actual->getDescription());
        $this->assertSame(2, $actual->getQuantity());
        $this->assertSame(LineItemType::PHYSICAL, $actual->getType());
        $this->assertSame('SW10001', $actual->getSku());
        $this->assertSame('cart-line-1', $actual->getShopwareLineItemId());
        $this->assertEquals(new Money(20.00, 'EUR'), $actual->getAmount());
    }

    public function testCartLineItemWithoutProductNumberFallsBackToTheLineItemId(): void
    {
        $cartLineItem = new CartLineItem('cart-line-1', CartLineItem::PRODUCT_LINE_ITEM_TYPE);
        $cartLineItem->setLabel('Custom product');
        $cartLineItem->setPrice($this->grossPrice(10.00, 10.00, 19.0));

        $actual = LineItem::fromCartLineItem($cartLineItem, $this->euro());

        $this->assertSame('cart-line-1', $actual->getSku());
    }

    public function testCanCreateFromCartDelivery(): void
    {
        $actual = LineItem::fromCartDelivery($this->cartDelivery('DHL', 4.99), $this->euro());

        $this->assertSame('DHL', $actual->getDescription());
        $this->assertSame(LineItemType::SHIPPING, $actual->getType());
        $this->assertSame('mol-delivery-fake-shipping-method-id', $actual->getSku());
        $this->assertEquals(new Money(4.99, 'EUR'), $actual->getAmount());
    }

    public function testNegativeCartDeliveryCostsBecomeADiscountLine(): void
    {
        $actual = LineItem::fromCartDelivery($this->cartDelivery('DHL', -4.99), $this->euro());

        $this->assertSame(LineItemType::DISCOUNT, $actual->getType());
    }

    public function testCartDeliveryFallsBackToShippingWhenTheNameIsEmpty(): void
    {
        $actual = LineItem::fromCartDelivery($this->cartDelivery('   ', 4.99), $this->euro());

        $this->assertSame('Shipping', $actual->getDescription());
    }

    public function testCanCreateFromOrdersApiResponse(): void
    {
        $actual = LineItem::createFromClientResponse([
            'id' => 'odl_1',
            'name' => 'Product A',
            'type' => 'physical',
            'quantity' => 3,
            'sku' => 'SW10001',
            'unitPrice' => ['value' => '10.00', 'currency' => 'EUR'],
            'totalAmount' => ['value' => '30.00', 'currency' => 'EUR'],
            'metadata' => ['orderLineItemId' => 'sw-line-1'],
        ]);

        $this->assertSame('odl_1', $actual->getId());
        $this->assertSame('Product A', $actual->getDescription());
        $this->assertSame(LineItemType::PHYSICAL, $actual->getType());
        $this->assertSame(3, $actual->getQuantity());
        $this->assertSame('SW10001', $actual->getSku());
        $this->assertSame('sw-line-1', $actual->getShopwareLineItemId());
        $this->assertEquals(new Money(10.00, 'EUR'), $actual->getUnitPrice());
        $this->assertEquals(new Money(30.00, 'EUR'), $actual->getAmount());
    }

    public function testSessionsApiResponseUsesDescriptionInsteadOfName(): void
    {
        $actual = LineItem::createFromClientResponse(['description' => 'Product B']);

        $this->assertSame('Product B', $actual->getDescription());
    }

    public function testResponseMetadataIsDecodedWhenMollieReturnsItAsAJsonString(): void
    {
        $actual = LineItem::createFromClientResponse([
            'metadata' => json_encode(['orderLineItemId' => 'sw-line-2']),
        ]);

        $this->assertSame('sw-line-2', $actual->getShopwareLineItemId());
    }

    public function testResponseWithAnUnknownTypeKeepsThePhysicalDefault(): void
    {
        $actual = LineItem::createFromClientResponse(['type' => 'not-a-mollie-type']);

        $this->assertSame(LineItemType::PHYSICAL, $actual->getType());
    }

    public function testResponseWithoutATypeKeepsThePhysicalDefault(): void
    {
        $actual = LineItem::createFromClientResponse([]);

        $this->assertSame(LineItemType::PHYSICAL, $actual->getType());
    }

    public function testEmptyResponseFallsBackToASingleItemWithoutPrice(): void
    {
        $actual = LineItem::createFromClientResponse([]);

        $this->assertSame('', $actual->getDescription());
        $this->assertSame(1, $actual->getQuantity());
        $this->assertSame('', $actual->getId());
        $this->assertSame('', $actual->getShopwareLineItemId());
        $this->assertEquals(new Money(0.0, ''), $actual->getAmount());
    }

    public function testResponseCarriesTheShippedRefundedAndCanceledQuantities(): void
    {
        $actual = LineItem::createFromClientResponse([
            'quantity' => 5,
            'quantityShipped' => 3,
            'quantityRefunded' => 1,
            'quantityCanceled' => 1,
            'shippableQuantity' => 2,
            'refundableQuantity' => 3,
            'cancelableQuantity' => 1,
            'amountShipped' => ['value' => '30.00', 'currency' => 'EUR'],
            'amountRefunded' => ['value' => '10.00', 'currency' => 'EUR'],
            'amountCanceled' => ['value' => '10.00', 'currency' => 'EUR'],
        ]);

        $this->assertSame(3, $actual->getQuantityShipped());
        $this->assertSame(1, $actual->getQuantityRefunded());
        $this->assertSame(1, $actual->getQuantityCanceled());
        $this->assertSame(2, $actual->getShippableQuantity());
        $this->assertSame(3, $actual->getRefundableQuantity());
        $this->assertSame(1, $actual->getCancelableQuantity());
        $this->assertEquals(new Money(30.00, 'EUR'), $actual->getAmountShipped());
        $this->assertEquals(new Money(10.00, 'EUR'), $actual->getAmountRefunded());
        $this->assertEquals(new Money(10.00, 'EUR'), $actual->getAmountCanceled());
    }

    public function testResponseWithoutAmountsLeavesThemUnset(): void
    {
        $actual = LineItem::createFromClientResponse([]);

        $this->assertNull($actual->getAmountShipped());
        $this->assertNull($actual->getAmountRefunded());
        $this->assertNull($actual->getAmountCanceled());
    }

    public function testJsonSerializeRenamesTheAmountToTotalAmount(): void
    {
        $lineItem = new LineItem('Product A', 2, new Money(10.00, 'EUR'), new Money(20.00, 'EUR'));
        $lineItem->setSku('SW10001');

        $serialized = json_decode((string) json_encode($lineItem), true);

        $this->assertSame(['value' => '20.00', 'currency' => 'EUR'], $serialized['totalAmount']);
        $this->assertArrayNotHasKey('amount', $serialized);
    }

    public function testJsonSerializeDropsEmptyAndZeroFields(): void
    {
        $lineItem = new LineItem('Product A', 2, new Money(10.00, 'EUR'), new Money(20.00, 'EUR'));
        $lineItem->setSku('SW10001');

        $serialized = json_decode((string) json_encode($lineItem), true);

        $this->assertEqualsCanonicalizing(['type', 'sku', 'description', 'quantity', 'unitPrice', 'totalAmount'], array_keys($serialized));
    }

    public function testJsonSerializeDoesNotLeakTheInternalMetadata(): void
    {
        $lineItem = new LineItem('Product A', 1, new Money(10.00, 'EUR'), new Money(10.00, 'EUR'));
        $lineItem->setSku('SW10001');
        $lineItem->setShopwareLineItemId('sw-line-1');

        $serialized = json_decode((string) json_encode($lineItem), true);

        $this->assertArrayNotHasKey('metadata', $serialized);
        $this->assertSame('sw-line-1', $lineItem->getShopwareLineItemId());
    }

    public function testDeliveryScopedPromotionIsRecognisedAsAPlaceholder(): void
    {
        $orderLineItem = $this->orderRepository->getDeliveryDiscountPromotionLineItem('Free shipping');

        $this->assertTrue(LineItem::isDeliveryDiscountPlaceholder($orderLineItem));
    }

    public function testCartScopedPromotionIsNoDeliveryPlaceholder(): void
    {
        $orderLineItem = $this->orderRepository->getDeliveryDiscountPromotionLineItem('10% off');
        $orderLineItem->setPayload(['discountScope' => 'cart']);

        $this->assertFalse(LineItem::isDeliveryDiscountPlaceholder($orderLineItem));
    }

    public function testProductLineIsNoDeliveryPlaceholder(): void
    {
        $orderLineItem = $this->orderRepository->getDeliveryDiscountPromotionLineItem('Free shipping');
        $orderLineItem->setType('product');

        $this->assertFalse(LineItem::isDeliveryDiscountPlaceholder($orderLineItem));
    }

    public function testDeliveryDiscountLabelIsNullWithoutAPlaceholder(): void
    {
        $orderLineItems = new OrderLineItemCollection([$this->orderRepository->getLineItems()->first()]);

        $this->assertNull(LineItem::resolveDeliveryDiscountLabel($orderLineItems));
    }

    public function testDeliveryDiscountLabelsAreJoined(): void
    {
        $first = $this->orderRepository->getDeliveryDiscountPromotionLineItem('Free shipping');
        $second = $this->orderRepository->getDeliveryDiscountPromotionLineItem('Shipping voucher');
        $second->setId('second-shipping-promotion');

        $label = LineItem::resolveDeliveryDiscountLabel(new OrderLineItemCollection([$first, $second]));

        $this->assertSame('Free shipping, Shipping voucher', $label);
    }

    public function testLineWithoutAnyTaxHasNoVatRate(): void
    {
        $orderLineItem = $this->orderLineItemWithTaxes(10.00, new CalculatedTaxCollection());

        $actual = LineItem::fromOrderLine($orderLineItem, $this->euro());

        $this->assertArrayNotHasKey('vatRate', $actual->jsonSerialize());
        $this->assertArrayNotHasKey('vatAmount', $actual->jsonSerialize());
    }

    public function testGrossLineWithTwoTaxRatesGetsTheBlendedRate(): void
    {
        $taxes = new CalculatedTaxCollection([
            new CalculatedTax(1.60, 19.0, 10.00),
            new CalculatedTax(0.65, 7.0, 10.00),
        ]);
        $orderLineItem = $this->orderLineItemWithTaxes(20.00, $taxes);

        $actual = LineItem::fromOrderLine($orderLineItem, $this->euro(), CartPrice::TAX_STATE_GROSS);

        $this->assertSame('12.68', $actual->getVatRate());
        $this->assertSame(['value' => '2.25', 'currency' => 'EUR'], $actual->getVatAmount()->toArray());
    }

    public function testLineWhoseTaxesCancelEachOtherOutHasNoVatRate(): void
    {
        // Two different rates on purpose: CalculatedTaxCollection is keyed by tax rate, so two
        // entries with the same rate would collapse into one and never reach the blending code.
        $taxes = new CalculatedTaxCollection([
            new CalculatedTax(1.60, 19.0, 10.00),
            new CalculatedTax(-1.60, 7.0, -10.00),
        ]);
        $orderLineItem = $this->orderLineItemWithTaxes(0.00, $taxes);

        $actual = LineItem::fromOrderLine($orderLineItem, $this->euro(), CartPrice::TAX_STATE_GROSS);

        $this->assertArrayNotHasKey('vatRate', $actual->jsonSerialize());
    }

    private function euro(): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');

        return $currency;
    }

    private function grossPrice(float $unitPrice, float $totalPrice, float $taxRate, int $quantity = 1): CalculatedPrice
    {
        $taxAmount = $totalPrice * $taxRate / (100 + $taxRate);
        $taxes = new CalculatedTaxCollection([new CalculatedTax(round($taxAmount, 2), $taxRate, $totalPrice)]);

        return new CalculatedPrice($unitPrice, $totalPrice, $taxes, new TaxRuleCollection(), $quantity);
    }

    private function cartDelivery(string $shippingMethodName, float $shippingCosts): Delivery
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fake-shipping-method-id');
        $shippingMethod->setName($shippingMethodName);

        $country = new CountryEntity();
        $country->setIso('DE');

        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable('2026-01-01'), new \DateTimeImmutable('2026-01-02')),
            $shippingMethod,
            ShippingLocation::createFromCountry($country),
            $this->grossPrice($shippingCosts, $shippingCosts, 19.0)
        );
    }

    private function orderLineItemWithTaxes(float $totalPrice, CalculatedTaxCollection $taxes): OrderLineItemEntity
    {
        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setId('tax-line-item-id');
        $orderLineItem->setLabel('Tax line');
        $orderLineItem->setPrice(new CalculatedPrice($totalPrice, $totalPrice, $taxes, new TaxRuleCollection(), 1));

        return $orderLineItem;
    }
}
