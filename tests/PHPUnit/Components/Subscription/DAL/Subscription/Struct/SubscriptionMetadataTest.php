<?php

namespace MolliePayments\Tests\Components\Subscription\DAL\Subscription\Struct;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\IntervalType;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\SubscriptionMetadata;
use PHPUnit\Framework\TestCase;

class SubscriptionMetadataTest extends TestCase
{


    /**
     * This test verifies that the array structure
     * for the JSON in the database is correctly built
     *
     * @return void
     */
    public function testArrayStructure(): void
    {
        $meta = new SubscriptionMetadata('2022-05-01', 1, IntervalType::MONTHS, 5, 'tr_123');

        $expected = [
            'start_date' => '2022-05-01',
            'interval_value' => 1,
            'interval_unit' => IntervalType::MONTHS,
            'times' => 5,
            'tmp_transaction' => 'tr_123',
        ];
        static::assertSame($expected, $meta->toArray());
    }

    /**
     * This test verifies that our temporary transaction ID
     * is skipped if its empty. This is really only a temporary data object,
     * that should not even exist in the JSON if empty.
     *
     * @return void
     */
    public function testSkipTmpTransactionInArrayStructure(): void
    {
        $meta = new SubscriptionMetadata('', 1, IntervalType::MONTHS, null, '');

        static::assertArrayNotHasKey('tmp_transaction', $meta->toArray());
    }

    /**
     * This test verifies that our times key is created even
     * if the value is NULL.
     *
     * @return void
     */
    public function testTimesWithNullInArrayStructure(): void
    {
        $meta = new SubscriptionMetadata('2022-05-01', 1, IntervalType::MONTHS, null, 'tr_123');

        static::assertArrayHasKey('times', $meta->toArray());
        static::assertSame(null, $meta->toArray()['times']);
    }

}
