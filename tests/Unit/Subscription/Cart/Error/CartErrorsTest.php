<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Cart\Error;

use Mollie\Shopware\Component\Subscription\Cart\Error\InvalidGuestAccountError;
use Mollie\Shopware\Component\Subscription\Cart\Error\InvalidPaymentMethodError;
use Mollie\Shopware\Component\Subscription\Cart\Error\MixedCartBlockError;
use Mollie\Shopware\Component\Subscription\Cart\Error\PaymentMethodAvailabilityNotice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;

#[CoversClass(InvalidGuestAccountError::class)]
#[CoversClass(InvalidPaymentMethodError::class)]
#[CoversClass(MixedCartBlockError::class)]
#[CoversClass(PaymentMethodAvailabilityNotice::class)]
final class CartErrorsTest extends TestCase
{
    public function testInvalidGuestAccountErrorBlocksOrder(): void
    {
        $error = new InvalidGuestAccountError();

        $this->assertTrue($error->blockOrder());
        $this->assertSame(Error::LEVEL_ERROR, $error->getLevel());
        $this->assertSame('mollie-payments-cart-guest-account', $error->getId());
        $this->assertSame('mollie-payments-cart-guest-account', $error->getMessageKey());
        $this->assertSame([], $error->getParameters());
    }

    public function testInvalidPaymentMethodErrorBlocksOrder(): void
    {
        $error = new InvalidPaymentMethodError();

        $this->assertTrue($error->blockOrder());
        $this->assertSame(Error::LEVEL_ERROR, $error->getLevel());
        $this->assertSame('mollie-payments-cart-error-method-invalid', $error->getId());
        $this->assertSame('mollie-payments-cart-error-method-invalid', $error->getMessageKey());
        $this->assertSame([], $error->getParameters());
    }

    public function testMixedCartBlockErrorBlocksOrder(): void
    {
        $error = new MixedCartBlockError();

        $this->assertTrue($error->blockOrder());
        $this->assertSame(Error::LEVEL_ERROR, $error->getLevel());
        $this->assertSame('mollie-payments-cart-error-mixedcart', $error->getId());
        $this->assertSame('mollie-payments-cart-error-mixedcart', $error->getMessageKey());
        $this->assertSame([], $error->getParameters());
    }

    public function testPaymentMethodAvailabilityNoticeDoesNotBlockOrder(): void
    {
        $error = new PaymentMethodAvailabilityNotice('line-item-abc');

        $this->assertFalse($error->blockOrder());
        $this->assertSame(Error::LEVEL_NOTICE, $error->getLevel());
        $this->assertSame('line-item-abc', $error->getId());
        $this->assertSame('mollie-payments-cart-error-paymentmethod-availability', $error->getMessageKey());
        $this->assertSame(['lineItemId' => 'line-item-abc'], $error->getParameters());
    }
}
