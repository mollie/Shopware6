<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class PaymentStatusTest extends TestCase
{
    public function testFailedStatus(): void
    {
        $paidStatus = new PaymentStatus(PaymentStatus::PAID);
        $failedStatus = new PaymentStatus(PaymentStatus::FAILED);
        $canceledStatus = new PaymentStatus(PaymentStatus::CANCELED);
        $expiredStatus = new PaymentStatus(PaymentStatus::EXPIRED);

        $this->assertFalse($paidStatus->isFailed());
        $this->assertTrue($failedStatus->isFailed());
        $this->assertTrue($canceledStatus->isFailed());
        $this->assertTrue($expiredStatus->isFailed());
    }

    public function testCanceledStatus(): void
    {
        $paidStatus = new PaymentStatus(PaymentStatus::PAID);
        $failedStatus = new PaymentStatus(PaymentStatus::FAILED);
        $canceledStatus = new PaymentStatus(PaymentStatus::CANCELED);
        $expiredStatus = new PaymentStatus(PaymentStatus::EXPIRED);

        $this->assertFalse($paidStatus->isCancelled());
        $this->assertFalse($failedStatus->isCancelled());
        $this->assertTrue($canceledStatus->isCancelled());
        $this->assertFalse($expiredStatus->isCancelled());
    }

    public function testInvalidStatus(): void
    {
        $this->expectExceptionMessageMatches('/invalid is not a valid value/');

        $invalid = new PaymentStatus('invalid');
    }

    public function testShopwareHandlerMethods(): void
    {
        $paidStatus = new PaymentStatus(PaymentStatus::PAID);
        $authorizedStatus = new PaymentStatus(PaymentStatus::AUTHORIZED);
        $canceledStatus = new PaymentStatus(PaymentStatus::CANCELED);
        $failedStatus = new PaymentStatus(PaymentStatus::FAILED);
        $expiredStatus = new PaymentStatus(PaymentStatus::EXPIRED);

        $this->assertSame('paid', $paidStatus->getShopwareHandlerMethod());
        $this->assertSame('authorize', $authorizedStatus->getShopwareHandlerMethod());
        $this->assertSame('cancel', $canceledStatus->getShopwareHandlerMethod());
        $this->assertSame('fail', $failedStatus->getShopwareHandlerMethod());
        $this->assertSame('', $expiredStatus->getShopwareHandlerMethod());
    }
}
