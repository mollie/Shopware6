<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Transaction\Exception;

use Mollie\Shopware\Component\Transaction\Exception\OrderWithoutCustomerException;
use Mollie\Shopware\Component\Transaction\Exception\OrderWithoutDeliveriesException;
use Mollie\Shopware\Component\Transaction\Exception\OrderWithoutTransactionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderWithoutCustomerException::class)]
#[CoversClass(OrderWithoutDeliveriesException::class)]
#[CoversClass(OrderWithoutTransactionException::class)]
final class TransactionExceptionsTest extends TestCase
{
    public function testOrderWithoutCustomerExceptionContainsOrderId(): void
    {
        $exception = new OrderWithoutCustomerException('order-abc');

        $this->assertStringContainsString('order-abc', $exception->getMessage());
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testOrderWithoutDeliveriesExceptionContainsOrderId(): void
    {
        $exception = new OrderWithoutDeliveriesException('order-def');

        $this->assertStringContainsString('order-def', $exception->getMessage());
    }

    public function testOrderWithoutTransactionExceptionContainsOrderId(): void
    {
        $exception = new OrderWithoutTransactionException('order-ghi');

        $this->assertStringContainsString('order-ghi', $exception->getMessage());
    }

    public function testExceptionsAcceptCustomCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('root cause');

        $exception = new OrderWithoutCustomerException('order-123', 99, $previous);

        $this->assertSame(99, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
