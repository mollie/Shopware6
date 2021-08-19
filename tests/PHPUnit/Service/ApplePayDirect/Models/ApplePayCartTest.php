<?php

namespace Kiener\MolliePayments\Tests\Service\ApplePayDirect\Models;

use Kiener\MolliePayments\Service\ApplePayDirect\Models\ApplePayCart;
use PHPUnit\Framework\TestCase;

class ApplePayCartTest extends TestCase
{

    /**
     * This test verifies that the taxes
     * can be set correctly.
     */
    public function testProducts()
    {
        $cart = new ApplePayCart();
        $cart->addItem('123', 'T-Shirt', 3, 10);
        $cart->addItem('456', 'Pants', 1, 20);

        $this->assertCount(2, $cart->getItems());

        $this->assertEquals('T-Shirt', $cart->getItems()[0]->getName());
        $this->assertEquals(3, $cart->getItems()[0]->getQuantity());
        $this->assertEquals(10, $cart->getItems()[0]->getPrice());
    }

    /**
     * This test verifies that the taxes
     * can be set correctly.
     */
    public function testTaxes()
    {
        $cart = new ApplePayCart();
        $cart->setTaxes(4.49);

        $this->assertEquals('', $cart->getTaxes()->getName());
        $this->assertEquals(1, $cart->getTaxes()->getQuantity());
        $this->assertEquals(4.49, $cart->getTaxes()->getPrice());
    }

    /**
     * This test verifies that the shippings
     * can be set correctly
     */
    public function testShippings()
    {
        $cart = new ApplePayCart();
        $cart->addShipping('My Shipping 1', 3.49);
        $cart->addShipping('My Shipping 2', 1);

        $this->assertCount(2, $cart->getShippings());

        $this->assertEquals('My Shipping 1', $cart->getShippings()[0]->getName());
        $this->assertEquals(1, $cart->getShippings()[0]->getQuantity());
        $this->assertEquals(3.49, $cart->getShippings()[0]->getPrice());
    }


    /**
     * This test verifies that our total amount
     * or our products is correct.
     */
    public function testProductAmount()
    {
        $cart = new ApplePayCart();
        $cart->addItem('', '', 3, 10);
        $cart->addItem('', '', 1, 20);

        $this->assertEquals(50, $cart->getProductAmount());
    }

    /**
     * This test verifies that our total shipping amount
     * is correct.
     */
    public function testShippingAmount()
    {
        $cart = new ApplePayCart();
        $cart->addShipping('My Shipping 1', 3.49);
        $cart->addShipping('My Shipping 2', 1);

        $this->assertEquals(4.49, $cart->getShippingAmount());
    }

    /**
     * This test verifies that our total amount
     * is calculated correctly.
     */
    public function testTotalAmount()
    {
        $cart = new ApplePayCart();
        $cart->addItem('', '', 3, 10);
        $cart->addShipping('My Shipping 1', 3.49);

        $this->assertEquals(33.49, $cart->getAmount());
    }

}
