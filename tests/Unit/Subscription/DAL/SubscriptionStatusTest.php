<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\DAL;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionStatus::class)]
class SubscriptionStatusTest extends TestCase
{
    /**
     * This test verifies that our plugin status enum values
     * have the correct string values and that these are not
     * touched without recognizing it.
     */
    #[DataProvider('getStatusStrings')]
    public function testStatusValues(string $expected, string $status): void
    {
        static::assertSame($expected, $status);
    }

    /**
     * @return array[]
     */
    public static function getStatusStrings(): array
    {
        return [
            ['pending', SubscriptionStatus::PENDING],
            ['active', SubscriptionStatus::ACTIVE],
            ['suspended', SubscriptionStatus::SUSPENDED],
            ['completed', SubscriptionStatus::COMPLETED],
            ['canceled', SubscriptionStatus::CANCELED],
            ['paused', SubscriptionStatus::PAUSED],
            ['resumed', SubscriptionStatus::RESUMED],
            ['skipped', SubscriptionStatus::SKIPPED],
        ];
    }

    /**
     * This test verifies that a status from the Mollie enum is
     * correctly converted into our advanced plugin status enum.
     */
    #[DataProvider('getMollieStatus')]
    public function testFromMollieStatus(string $expected, string $mollieStatus): void
    {
        $convertedStatus = SubscriptionStatus::fromMollieStatus($mollieStatus);

        static::assertSame($expected, $convertedStatus);
    }

    /**
     * @return array[]
     */
    public static function getMollieStatus(): array
    {
        return [
            [SubscriptionStatus::PENDING, 'pending'],
            [SubscriptionStatus::ACTIVE, 'active'],
            [SubscriptionStatus::SUSPENDED, 'suspended'],
            [SubscriptionStatus::COMPLETED, 'completed'],
            [SubscriptionStatus::CANCELED, 'canceled'],
        ];
    }

    /**
     * This test verifies that we use PENDING if somehow Mollie tells
     * us an unknown status.
     */
    public function testUnknownMollieStatusLeadsToPending(): void
    {
        $convertedStatus = SubscriptionStatus::fromMollieStatus('unknown');

        static::assertSame(SubscriptionStatus::PENDING, $convertedStatus);
    }
}
