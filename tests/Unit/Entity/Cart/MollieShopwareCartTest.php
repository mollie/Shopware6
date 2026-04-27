<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Entity\Cart;

use Mollie\Shopware\Entity\Cart\MollieShopwareCart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;

#[CoversClass(MollieShopwareCart::class)]
final class MollieShopwareCartTest extends TestCase
{
    public function testGetCartReturnsOriginalCart(): void
    {
        $cart = new Cart('test-token');
        $mollieCart = new MollieShopwareCart($cart);

        $this->assertSame($cart, $mollieCart->getCart());
    }

    public function testSingleProductExpressCheckoutDefaultsFalse(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));

        $this->assertFalse($mollieCart->isSingleProductExpressCheckout());
    }

    public function testSetSingleProductExpressCheckout(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));
        $mollieCart->setSingleProductExpressCheckout(true);

        $this->assertTrue($mollieCart->isSingleProductExpressCheckout());
    }

    public function testDataProtectionDefaultsZero(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));

        $this->assertSame(0, $mollieCart->isDataProtectionAccepted());
    }

    public function testSetDataProtectionAccepted(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));
        $mollieCart->setDataProtectionAccepted(1);

        $this->assertSame(1, $mollieCart->isDataProtectionAccepted());
    }

    public function testPayPalExpressSessionIdDefaultsEmpty(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));

        $this->assertSame('', $mollieCart->getPayPalExpressSessionID());
    }

    public function testSetAndGetPayPalExpressSessionId(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));
        $mollieCart->setPayPalExpressSessionID('session-abc-123');

        $this->assertSame('session-abc-123', $mollieCart->getPayPalExpressSessionID());
    }

    public function testPayPalExpressAuthIdDefaultsEmpty(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));

        $this->assertSame('', $mollieCart->getPayPalExpressAuthId());
    }

    public function testSetAndGetPayPalExpressAuthenticateId(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));
        $mollieCart->setPayPalExpressAuthenticateId('auth-xyz-456');

        $this->assertSame('auth-xyz-456', $mollieCart->getPayPalExpressAuthId());
    }

    public function testIsPayPalExpressCompleteReturnsFalseWithoutSessionId(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));

        $this->assertFalse($mollieCart->isPayPalExpressComplete());
    }

    public function testIsPayPalExpressCompleteReturnsFalseWithSessionIdButNoAuthId(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));
        $mollieCart->setPayPalExpressSessionID('session-123');

        $this->assertFalse($mollieCart->isPayPalExpressComplete());
    }

    public function testIsPayPalExpressCompleteReturnsTrueWithBothIds(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));
        $mollieCart->setPayPalExpressSessionID('session-123');
        $mollieCart->setPayPalExpressAuthenticateId('auth-456');

        $this->assertTrue($mollieCart->isPayPalExpressComplete());
    }

    public function testIsPayPalExpressIncompleteReturnsTrueWithSessionButNoAuth(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));
        $mollieCart->setPayPalExpressSessionID('session-123');

        $this->assertTrue($mollieCart->isPayPalExpressIncomplete());
    }

    public function testIsPayPalExpressIncompleteReturnsFalseWithNoSession(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));

        $this->assertFalse($mollieCart->isPayPalExpressIncomplete());
    }

    public function testIsPayPalExpressIncompleteReturnsFalseWithBothIds(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));
        $mollieCart->setPayPalExpressSessionID('session-123');
        $mollieCart->setPayPalExpressAuthenticateId('auth-456');

        $this->assertFalse($mollieCart->isPayPalExpressIncomplete());
    }

    public function testClearPayPalExpress(): void
    {
        $mollieCart = new MollieShopwareCart(new Cart('test'));
        $mollieCart->setPayPalExpressSessionID('session-123');
        $mollieCart->setPayPalExpressAuthenticateId('auth-456');
        $mollieCart->setSingleProductExpressCheckout(true);

        $mollieCart->clearPayPalExpress();

        $this->assertSame('', $mollieCart->getPayPalExpressSessionID());
        $this->assertSame('', $mollieCart->getPayPalExpressAuthId());
        $this->assertFalse($mollieCart->isSingleProductExpressCheckout());
    }
}
