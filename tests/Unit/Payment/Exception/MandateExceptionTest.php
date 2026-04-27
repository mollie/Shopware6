<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Exception;

use Mollie\Shopware\Component\Payment\Mandate\MandateException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(MandateException::class)]
final class MandateExceptionTest extends TestCase
{
    public function testCustomerNotLoggedIn(): void
    {
        $exception = MandateException::customerNotLoggedIn();

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(MandateException::NO_CUSTOMER, $exception->getErrorCode());
    }

    public function testMollieCustomerIdNotSet(): void
    {
        $exception = MandateException::mollieCustomerIdNotSet('customer-001');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(MandateException::MISSING_MOLLIE_CUSTOMER_ID, $exception->getErrorCode());
    }

    public function testOneClickPaymentDisabled(): void
    {
        $exception = MandateException::oneClickPaymentDisabled('sc-xyz');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        $this->assertSame(MandateException::ONE_CLICK_DISABLED, $exception->getErrorCode());
    }

    public function testCustomerIdNotSetForProfile(): void
    {
        $exception = MandateException::customerIdNotSetForProfile('cust-001', 'pfl_abc');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }
}
