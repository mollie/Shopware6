<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\PayPalExpress;

use Mollie\Shopware\Component\Payment\PayPalExpress\PaypalExpressException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PaypalExpressException::class)]
final class PaypalExpressExceptionTest extends TestCase
{
    #[DataProvider('provideExceptions')]
    public function testExceptionHasCorrectErrorCodeAndStatus(
        PaypalExpressException $ex,
        string $expectedCode,
        int $expectedStatus,
    ): void {
        $this->assertSame($expectedCode, $ex->getErrorCode());
        $this->assertSame($expectedStatus, $ex->getStatusCode());
    }

    public static function provideExceptions(): array
    {
        $address = new \stdClass();
        $address->street = 'Main St 1';

        return [
            'payment-not-enabled' => [
                PaypalExpressException::paymentNotEnabled('sc-1'),
                PaypalExpressException::PAYMENT_METHOD_DISABLED,
                Response::HTTP_BAD_REQUEST,
            ],
            'cart-is-empty' => [
                PaypalExpressException::cartIsEmpty(),
                PaypalExpressException::EMPTY_CART,
                Response::HTTP_BAD_REQUEST,
            ],
            'missing-session-id' => [
                PaypalExpressException::missingSessionId(),
                PaypalExpressException::MISSING_SESSION_ID,
                Response::HTTP_BAD_REQUEST,
            ],
            'cart-session-id-is-empty' => [
                PaypalExpressException::cartSessionIdIsEmpty(),
                PaypalExpressException::MISSING_CART_SESSION_ID,
                Response::HTTP_BAD_REQUEST,
            ],
            'shipping-address-missing' => [
                PaypalExpressException::shippingAddressMissing(),
                PaypalExpressException::MISSING_SHIPPING_ADDRESS,
                Response::HTTP_BAD_REQUEST,
            ],
            'billing-address-missing' => [
                PaypalExpressException::billingAddressMissing(),
                PaypalExpressException::MISSING_BILLING_ADDRESS,
                Response::HTTP_BAD_REQUEST,
            ],
            'billing-address-error' => [
                PaypalExpressException::billingAddressError('parse error', $address),
                PaypalExpressException::BILLING_ADDRESS_PARSING_ERROR,
                Response::HTTP_BAD_REQUEST,
            ],
            'shipping-address-error' => [
                PaypalExpressException::shippingAddressError('parse error', $address),
                PaypalExpressException::SHIPPING_ADDRESS_PARSING_ERROR,
                Response::HTTP_BAD_REQUEST,
            ],
        ];
    }
}
