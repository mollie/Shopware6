<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Exception;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ApplePayDirectException::class)]
final class ApplePayDirectExceptionTest extends TestCase
{
    public function testInvalidCountryCode(): void
    {
        $exception = ApplePayDirectException::invalidCountryCode('ZZ');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(ApplePayDirectException::INVALID_SHIPPING_COUNTRY, $exception->getErrorCode());
    }

    public function testValidationUrlNotFound(): void
    {
        $exception = ApplePayDirectException::validationUrlNotFound();

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        $this->assertSame(ApplePayDirectException::INVALID_VALIDATION_URL, $exception->getErrorCode());
    }

    public function testSessionRequestFailed(): void
    {
        $previous = new \RuntimeException('timeout');
        $exception = ApplePayDirectException::sessionRequestFailed($previous);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(ApplePayDirectException::CREATE_SESSION_FAILED, $exception->getErrorCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testMissingShippingMethodIdentifier(): void
    {
        $exception = ApplePayDirectException::missingShippingMethodIdentifier();

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        $this->assertSame(ApplePayDirectException::MISSING_SHIPPING_METHOD, $exception->getErrorCode());
    }

    public function testPaymentTokenNotFound(): void
    {
        $exception = ApplePayDirectException::paymentTokenNotFound();

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        $this->assertSame(ApplePayDirectException::PAYMENT_TOKEN_NOT_FOUND, $exception->getErrorCode());
    }

    public function testPaymentDisabled(): void
    {
        $exception = ApplePayDirectException::paymentDisabled();

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        $this->assertSame(ApplePayDirectException::PAYMENT_DISABLED, $exception->getErrorCode());
    }

    public function testCustomerActionFailed(): void
    {
        $previous = new \RuntimeException('login failed');
        $exception = ApplePayDirectException::customerActionFailed($previous);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(ApplePayDirectException::CUSTOMER_ACTION_FAILED, $exception->getErrorCode());
    }

    public function testLoadCartFailed(): void
    {
        $previous = new \RuntimeException('cart error');
        $exception = ApplePayDirectException::loadCartFailed($previous);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(ApplePayDirectException::CART_ACTION_FAILED, $exception->getErrorCode());
    }

    public function testCreateOrderFailed(): void
    {
        $previous = new \RuntimeException('order error');
        $exception = ApplePayDirectException::createOrderFailed($previous);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(ApplePayDirectException::ORDER_ACTION_FAILED, $exception->getErrorCode());
    }

    public function testPaymentFailed(): void
    {
        $previous = new \RuntimeException('payment error');
        $exception = ApplePayDirectException::paymentFailed($previous, 'order-abc');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(ApplePayDirectException::PAYMENT_FAILED, $exception->getErrorCode());
    }

    public function testCountryCodeEmpty(): void
    {
        $exception = ApplePayDirectException::countryCodeEmpty();

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }
}
