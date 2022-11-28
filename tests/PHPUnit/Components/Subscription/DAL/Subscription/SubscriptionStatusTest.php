<?php

namespace MolliePayments\Tests\Components\Subscription\DAL\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Mollie\Api\Types\SubscriptionStatus as MollieSubscriptionStatus;
use PHPUnit\Framework\TestCase;


class SubscriptionStatusTest extends TestCase
{

    /**
     * @return array[]
     */
    public function getStatusStrings(): array
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
     * This test verifies that our plugin status enum values
     * have the correct string values and that these are not
     * touched without recognizing it.
     *
     * @dataProvider getStatusStrings
     * @param string $expected
     * @param string $status
     * @return void
     */
    public function testStatusValues(string $expected, string $status): void
    {
        static::assertSame($expected, $status);
    }


    /**
     * @return array[]
     */
    public function getMollieStatus(): array
    {
        return [
            [SubscriptionStatus::PENDING, MollieSubscriptionStatus::STATUS_PENDING],
            [SubscriptionStatus::ACTIVE, MollieSubscriptionStatus::STATUS_ACTIVE],
            [SubscriptionStatus::SUSPENDED, MollieSubscriptionStatus::STATUS_SUSPENDED],
            [SubscriptionStatus::COMPLETED, MollieSubscriptionStatus::STATUS_COMPLETED],
            [SubscriptionStatus::CANCELED, MollieSubscriptionStatus::STATUS_CANCELED],
        ];
    }

    /**
     * This test verifies that a status from the Mollie enum is
     * correctly converted into our advanced plugin status enum.
     *
     * @dataProvider getMollieStatus
     * @param string $expected
     * @param string $mollieStatus
     * @return void
     */
    public function testFromMollieStatus(string $expected, string $mollieStatus): void
    {
        $convertedStatus = SubscriptionStatus::fromMollieStatus($mollieStatus);

        static::assertSame($expected, $convertedStatus);
    }

    /**
     * This test verifies that we use PENDING if somehow Mollie tells
     * us an unknown status.
     *
     * @return void
     */
    public function testUnknownMollieStatusLeadsToPending(): void
    {
        $convertedStatus = SubscriptionStatus::fromMollieStatus('unknown');

        static::assertSame(SubscriptionStatus::PENDING, $convertedStatus);
    }

}