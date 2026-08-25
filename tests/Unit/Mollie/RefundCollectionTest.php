<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Refund;
use Mollie\Shopware\Component\Mollie\RefundCollection;
use Mollie\Shopware\Component\Mollie\RefundStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundCollection::class)]
final class RefundCollectionTest extends TestCase
{
    public function testOnlyCompletedRefundsCountTowardsTheRefundedSum(): void
    {
        $collection = new RefundCollection([
            $this->refund('re-1', RefundStatus::Refunded, 10.0),
            $this->refund('re-2', RefundStatus::Pending, 5.0),
            $this->refund('re-3', RefundStatus::Failed, 99.0),
            $this->refund('re-4', RefundStatus::Refunded, 2.5),
        ]);

        $this->assertSame(12.5, $collection->getSumRefunded());
    }

    public function testRefundedSumIsRoundedToTwoDecimals(): void
    {
        $collection = new RefundCollection([
            $this->refund('re-1', RefundStatus::Refunded, 0.1),
            $this->refund('re-2', RefundStatus::Refunded, 0.2),
        ]);

        $this->assertSame(0.3, $collection->getSumRefunded());
    }

    public function testRefundedSumIsZeroWithoutAnyRefund(): void
    {
        $collection = new RefundCollection([]);

        $this->assertSame(0.0, $collection->getSumRefunded());
    }

    public function testQueuedAndPendingRefundsCountTowardsThePendingSum(): void
    {
        $collection = new RefundCollection([
            $this->refund('re-1', RefundStatus::Pending, 10.0),
            $this->refund('re-2', RefundStatus::Queued, 5.0),
        ]);

        $this->assertSame(15.0, $collection->getSumPending());
    }

    public function testProcessingRefundIsNeitherPendingNorRefunded(): void
    {
        $collection = new RefundCollection([$this->refund('re-1', RefundStatus::Processing, 10.0)]);

        $this->assertSame(0.0, $collection->getSumPending());
        $this->assertSame(0.0, $collection->getSumRefunded());
    }

    public function testRefundIsFoundByItsMollieId(): void
    {
        $collection = new RefundCollection([
            $this->refund('re-1', RefundStatus::Refunded, 10.0),
            $this->refund('re-2', RefundStatus::Refunded, 20.0),
        ]);

        $this->assertSame(20.0, $collection->findByMollieId('re-2')?->getAmount()->getValue());
    }

    public function testUnknownMollieIdFindsNoRefund(): void
    {
        $collection = new RefundCollection([$this->refund('re-1', RefundStatus::Refunded, 10.0)]);

        $this->assertNull($collection->findByMollieId('re-99'));
    }

    public function testRefundIsFoundByTheShopwareReturnItWasCreatedFor(): void
    {
        $collection = new RefundCollection([
            $this->refund('re-1', RefundStatus::Refunded, 10.0),
            $this->refund('re-2', RefundStatus::Refunded, 20.0, ['swagReturnId' => 'return-1']),
        ]);

        $this->assertSame('re-2', $collection->findByReturnId('return-1')?->getId());
    }

    public function testRefundWithoutAReturnIdIsNotFoundByReturnId(): void
    {
        $collection = new RefundCollection([$this->refund('re-1', RefundStatus::Refunded, 10.0)]);

        $this->assertNull($collection->findByReturnId('return-1'));
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function refund(string $id, RefundStatus $status, float $amount, array $metadata = []): Refund
    {
        return new Refund(
            $id,
            'tr-1',
            $status,
            new Money($amount, 'EUR'),
            'Refund',
            new \DateTimeImmutable('2026-08-25 10:00:00'),
            $metadata
        );
    }
}
