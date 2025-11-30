<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\PaymentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaymentStatus::class)]
final class PaymentStatusTest extends TestCase
{
    public function testFailedStatus(): void
    {
        $paidStatus = PaymentStatus::PAID;
        $failedStatus = PaymentStatus::FAILED;
        $canceledStatus = PaymentStatus::CANCELED;
        $expiredStatus = PaymentStatus::EXPIRED;

        $this->assertFalse($paidStatus->isFailed());
        $this->assertTrue($failedStatus->isFailed());
        $this->assertTrue($canceledStatus->isFailed());
        $this->assertTrue($expiredStatus->isFailed());
    }

    public function testCanceledStatus(): void
    {
        $paidStatus = PaymentStatus::PAID;
        $failedStatus = PaymentStatus::FAILED;
        $canceledStatus = PaymentStatus::CANCELED;
        $expiredStatus = PaymentStatus::EXPIRED;

        $this->assertFalse($paidStatus->isCanceled());
        $this->assertFalse($failedStatus->isCanceled());
        $this->assertTrue($canceledStatus->isCanceled());
        $this->assertFalse($expiredStatus->isCanceled());
    }

    public function testInvalidStatus(): void
    {
        $this->expectExceptionMessageMatches('/"invalid" is not a valid/');

        $invalid = PaymentStatus::from('invalid');
    }

    public function testShopwareHandlerMethods(): void
    {
        $paidStatus = PaymentStatus::PAID;
        $authorizedStatus = PaymentStatus::AUTHORIZED;
        $canceledStatus = PaymentStatus::CANCELED;
        $failedStatus = PaymentStatus::FAILED;
        $expiredStatus = PaymentStatus::EXPIRED;

        $this->assertSame('paid', $paidStatus->getShopwareHandlerMethod());
        $this->assertSame('authorize', $authorizedStatus->getShopwareHandlerMethod());
        $this->assertSame('cancel', $canceledStatus->getShopwareHandlerMethod());
        $this->assertSame('fail', $failedStatus->getShopwareHandlerMethod());
        $this->assertSame('', $expiredStatus->getShopwareHandlerMethod());
    }
}
