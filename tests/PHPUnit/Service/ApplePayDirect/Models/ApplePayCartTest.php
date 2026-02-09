<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service\ApplePayDirect\Models;

use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayCart;
use PHPUnit\Framework\TestCase;

class ApplePayCartTest extends TestCase
{
    /**
     * This test verifies that the taxes
     * can be set correctly.
     */
    public function testProducts()
    {
        $cart = new ApplePayCart(true);
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
        $cart = new ApplePayCart(true);
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
        $cart = new ApplePayCart(true);
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
        $cart = new ApplePayCart(true);
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
        $cart = new ApplePayCart(true);
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
        $cart = new ApplePayCart(true);
        $cart->addItem('', '', 3, 10);
        $cart->addShipping('My Shipping 1', 3.49);
        $cart->setTaxes(4);

        $this->assertEquals(33.49, $cart->getAmount());
    }

    /**
     * If the shop is configured to have a customer group with NET display
     * then our total amount does also need to have the TAX amount added.
     * In gross display mode, the line items are already including the tax amount.
     */
    public function testTotalAmountWithNetDisplay(): void
    {
        $cart = new ApplePayCart(false);

        $cart->addItem('', '', 1, 20);
        $cart->addShipping('My Shipping 1', 5);
        $cart->setTaxes(4);

        // cart = 20 + 5 + 4 = 29
        $this->assertEquals(29, $cart->getAmount());
    }
}
