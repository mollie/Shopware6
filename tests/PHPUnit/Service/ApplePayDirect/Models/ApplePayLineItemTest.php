<?php

namespace MolliePayments\Tests\Service\ApplePayDirect\Models;

use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayLineItem;
use PHPUnit\Framework\TestCase;

class ApplePayLineItemTest extends TestCase
{
    /**
     * This test verifies that the setter
     * and getter for this property work correctly.
     */
    public function testNumber()
    {
        $item = new ApplePayLineItem('123', 'T-Shirt', 3, 10);

        $this->assertEquals('123', $item->getNumber());
    }

    /**
     * This test verifies that the setter
     * and getter for this property work correctly.
     */
    public function testName()
    {
        $item = new ApplePayLineItem('123', 'T-Shirt', 3, 10);

        $this->assertEquals('T-Shirt', $item->getName());
    }

    /**
     * This test verifies that the setter
     * and getter for this property work correctly.
     */
    public function testQuantity()
    {
        $item = new ApplePayLineItem('123', 'T-Shirt', 3, 10);

        $this->assertEquals(3, $item->getQuantity());
    }

    /**
     * This test verifies that the setter
     * and getter for this property work correctly.
     */
    public function testPrice()
    {
        $item = new ApplePayLineItem('123', 'T-Shirt', 3, 10);

        $this->assertEquals(10, $item->getPrice());
    }
}
