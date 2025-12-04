<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Exception\MissingLineItemPriceException;
use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Exception\MissingCalculatedTaxException;
use Mollie\Shopware\Component\Mollie\Exception\MissingShippingMethodException;
use Mollie\Shopware\Unit\Mollie\Fake\FakeOrderRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(LineItem::class)]
final class LineItemTest extends TestCase
{
    private FakeOrderRepository $orderRepository;

    public function setUp(): void
    {
        $this->orderRepository = new FakeOrderRepository();
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
        $this->assertEquals($price, $lineItem->getTotalAmount());
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

    public function testExpectExceptionOnEmptyCalculatedTax(): void
    {
        $delivery = $this->orderRepository->getOrderDeliveryWithoutShippingCosts();
        $currency = new CurrencyEntity();
        $currency->setIsoCode('EUR');
        $this->expectException(MissingCalculatedTaxException::class);
        LineItem::fromDelivery($delivery, $currency);
    }

    public function testCanCreateFromDelivery(): void
    {
        $customerRepository = new \Mollie\Shopware\Unit\Mollie\Fake\FakeCustomerRepository();
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
            'totalAmount' => new Money(4.99, 'EUR'),
        ];

        $this->assertInstanceOf(LineItem::class, $actual);

        $this->assertSame($expected['description'], $actual->getDescription());
        $this->assertSame($expected['quantity'], $actual->getQuantity());
        $this->assertSame($expected['type'], $actual->getType()->value);
        $this->assertSame($expected['sku'], $actual->getSku());
        $this->assertEquals($expected['unitPrice'], $actual->getUnitPrice());
        $this->assertEquals($expected['totalAmount'], $actual->getTotalAmount());
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
            'totalAmount' => new Money(10.99, 'EUR'),
        ];

        $this->assertInstanceOf(LineItem::class, $actual);
        $this->assertSame($expected['description'], $actual->getDescription());
        $this->assertSame($expected['quantity'], $actual->getQuantity());
        $this->assertSame($expected['type'], $actual->getType()->value);
        $this->assertSame($expected['sku'], $actual->getSku());
        $this->assertEquals($expected['unitPrice'], $actual->getUnitPrice());
        $this->assertEquals($expected['totalAmount'], $actual->getTotalAmount());
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
}
