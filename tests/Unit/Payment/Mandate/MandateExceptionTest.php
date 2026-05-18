<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Mandate;

use Mollie\Shopware\Component\Payment\Mandate\MandateException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(MandateException::class)]
final class MandateExceptionTest extends TestCase
{
    #[DataProvider('provideExceptions')]
    public function testExceptionHasCorrectErrorCodeAndStatus(
        MandateException $ex,
        string $expectedCode,
        int $expectedStatus,
    ): void {
        $this->assertSame($expectedCode, $ex->getErrorCode());
        $this->assertSame($expectedStatus, $ex->getStatusCode());
    }

    public static function provideExceptions(): array
    {
        return [
            'customer-not-logged-in' => [
                MandateException::customerNotLoggedIn(),
                MandateException::NO_CUSTOMER,
                Response::HTTP_BAD_REQUEST,
            ],
            'mollie-customer-id-not-set' => [
                MandateException::mollieCustomerIdNotSet('CUST-001'),
                MandateException::MISSING_MOLLIE_CUSTOMER_ID,
                Response::HTTP_BAD_REQUEST,
            ],
            'one-click-payment-disabled' => [
                MandateException::oneClickPaymentDisabled('sc-1'),
                MandateException::ONE_CLICK_DISABLED,
                Response::HTTP_BAD_REQUEST,
            ],
            'customer-id-not-set-for-profile' => [
                MandateException::customerIdNotSetForProfile('CUST-002', 'prof-1'),
                MandateException::ONE_CLICK_DISABLED,
                Response::HTTP_BAD_REQUEST,
            ],
        ];
    }
}
