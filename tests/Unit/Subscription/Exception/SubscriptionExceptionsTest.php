<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Exception;

use Mollie\Shopware\Component\Subscription\Exception\SubscriptionDisabledException;
use Mollie\Shopware\Component\Subscription\Exception\SubscriptionNotFoundException;
use Mollie\Shopware\Component\Subscription\Exception\SubscriptionWithoutAddressException;
use Mollie\Shopware\Component\Subscription\Exception\SubscriptionWithoutOrderException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionDisabledException::class)]
#[CoversClass(SubscriptionNotFoundException::class)]
#[CoversClass(SubscriptionWithoutAddressException::class)]
#[CoversClass(SubscriptionWithoutOrderException::class)]
final class SubscriptionExceptionsTest extends TestCase
{
    public function testSubscriptionDisabledExceptionContainsSalesChannelId(): void
    {
        $exception = new SubscriptionDisabledException('sales-channel-123');

        $this->assertStringContainsString('sales-channel-123', $exception->getMessage());
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testSubscriptionNotFoundExceptionContainsSubscriptionId(): void
    {
        $exception = new SubscriptionNotFoundException('sub-abc-456');

        $this->assertStringContainsString('sub-abc-456', $exception->getMessage());
    }

    public function testSubscriptionWithoutAddressExceptionContainsSubscriptionId(): void
    {
        $exception = new SubscriptionWithoutAddressException('sub-xyz-789');

        $this->assertStringContainsString('sub-xyz-789', $exception->getMessage());
    }

    public function testSubscriptionWithoutOrderExceptionContainsSubscriptionId(): void
    {
        $exception = new SubscriptionWithoutOrderException('sub-order-001');

        $this->assertStringContainsString('sub-order-001', $exception->getMessage());
    }

    public function testExceptionsAcceptCustomCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('root cause');

        $exception = new SubscriptionNotFoundException('sub-123', 42, $previous);

        $this->assertSame(42, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
