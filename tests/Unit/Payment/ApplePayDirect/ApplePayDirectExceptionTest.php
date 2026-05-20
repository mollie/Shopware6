<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ApplePayDirect;

use Mollie\Shopware\Component\Payment\ApplePayDirect\ApplePayDirectException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(ApplePayDirectException::class)]
final class ApplePayDirectExceptionTest extends TestCase
{
    #[DataProvider('provideExceptions')]
    public function testExceptionHasCorrectErrorCodeAndStatus(
        ApplePayDirectException $ex,
        string $expectedCode,
        int $expectedStatus,
    ): void {
        $this->assertSame($expectedCode, $ex->getErrorCode());
        $this->assertSame($expectedStatus, $ex->getStatusCode());
    }

    public static function provideExceptions(): array
    {
        $cause = new \RuntimeException('cause');

        return [
            'invalid-country-code' => [
                ApplePayDirectException::invalidCountryCode('XX'),
                ApplePayDirectException::INVALID_SHIPPING_COUNTRY,
                Response::HTTP_BAD_REQUEST,
            ],
            'validation-url-not-found' => [
                ApplePayDirectException::validationUrlNotFound(),
                ApplePayDirectException::INVALID_VALIDATION_URL,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ],
            'session-request-failed' => [
                ApplePayDirectException::sessionRequestFailed($cause),
                ApplePayDirectException::CREATE_SESSION_FAILED,
                Response::HTTP_BAD_REQUEST,
            ],
            'missing-shipping-method-identifier' => [
                ApplePayDirectException::missingShippingMethodIdentifier(),
                ApplePayDirectException::MISSING_SHIPPING_METHOD,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ],
            'payment-token-not-found' => [
                ApplePayDirectException::paymentTokenNotFound(),
                ApplePayDirectException::PAYMENT_TOKEN_NOT_FOUND,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ],
            'payment-disabled' => [
                ApplePayDirectException::paymentDisabled(),
                ApplePayDirectException::PAYMENT_DISABLED,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ],
            'customer-action-failed' => [
                ApplePayDirectException::customerActionFailed($cause),
                ApplePayDirectException::CUSTOMER_ACTION_FAILED,
                Response::HTTP_BAD_REQUEST,
            ],
            'load-cart-failed' => [
                ApplePayDirectException::loadCartFailed($cause),
                ApplePayDirectException::CART_ACTION_FAILED,
                Response::HTTP_BAD_REQUEST,
            ],
            'create-order-failed' => [
                ApplePayDirectException::createOrderFailed($cause),
                ApplePayDirectException::ORDER_ACTION_FAILED,
                Response::HTTP_BAD_REQUEST,
            ],
            'payment-failed' => [
                ApplePayDirectException::paymentFailed($cause, 'order-1'),
                ApplePayDirectException::PAYMENT_FAILED,
                Response::HTTP_BAD_REQUEST,
            ],
            'country-code-empty' => [
                ApplePayDirectException::countryCodeEmpty(),
                ApplePayDirectException::ORDER_ACTION_FAILED,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ],
        ];
    }
}
