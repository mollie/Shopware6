<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Exception;

use Mollie\Shopware\Component\Payment\PayPalExpress\PaypalExpressException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PaypalExpressException::class)]
final class PaypalExpressExceptionTest extends TestCase
{
    public function testPaymentNotEnabled(): void
    {
        $exception = PaypalExpressException::paymentNotEnabled('sc-abc');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(PaypalExpressException::PAYMENT_METHOD_DISABLED, $exception->getErrorCode());
    }

    public function testCartIsEmpty(): void
    {
        $exception = PaypalExpressException::cartIsEmpty();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(PaypalExpressException::EMPTY_CART, $exception->getErrorCode());
    }

    public function testMissingSessionId(): void
    {
        $exception = PaypalExpressException::missingSessionId();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(PaypalExpressException::MISSING_SESSION_ID, $exception->getErrorCode());
    }

    public function testCartSessionIdIsEmpty(): void
    {
        $exception = PaypalExpressException::cartSessionIdIsEmpty();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(PaypalExpressException::MISSING_CART_SESSION_ID, $exception->getErrorCode());
    }

    public function testShippingAddressMissing(): void
    {
        $exception = PaypalExpressException::shippingAddressMissing();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(PaypalExpressException::MISSING_SHIPPING_ADDRESS, $exception->getErrorCode());
    }

    public function testBillingAddressMissing(): void
    {
        $exception = PaypalExpressException::billingAddressMissing();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(PaypalExpressException::MISSING_BILLING_ADDRESS, $exception->getErrorCode());
    }

    public function testBillingAddressError(): void
    {
        $address = new \stdClass();
        $address->street = '123 Main St';
        $exception = PaypalExpressException::billingAddressError('parsing failed', $address);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(PaypalExpressException::BILLING_ADDRESS_PARSING_ERROR, $exception->getErrorCode());
    }

    public function testShippingAddressError(): void
    {
        $address = new \stdClass();
        $address->street = '456 Oak Ave';
        $exception = PaypalExpressException::shippingAddressError('parsing failed', $address);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(PaypalExpressException::SHIPPING_ADDRESS_PARSING_ERROR, $exception->getErrorCode());
    }
}
