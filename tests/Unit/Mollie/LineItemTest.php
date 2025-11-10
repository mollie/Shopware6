<?php
declare(strict_types=1);

namespace Mollie\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\Money;
use PHPUnit\Framework\TestCase;

final class LineItemTest extends TestCase
{
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
        $this->assertSame('physical', (string) $lineItem->getType());
        $this->assertSame('pc', $lineItem->getQuantityUnit());
        $this->assertEquals($discountAmount, $lineItem->getDiscountAmount());
        $this->assertEquals($vatAmount, $lineItem->getVatAmount());
        $this->assertSame('7', $lineItem->getVatRate());
        $this->assertSame('test.jpg', $lineItem->getImageUrl());
        $this->assertSame('test.com/1234', $lineItem->getProductUrl());
        $this->assertSame('test', $lineItem->getSku());
    }
}
