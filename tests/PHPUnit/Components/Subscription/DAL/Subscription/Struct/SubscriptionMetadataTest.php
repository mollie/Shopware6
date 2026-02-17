<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\Subscription\DAL\Subscription\Struct;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\IntervalType;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use PHPUnit\Framework\TestCase;

class SubscriptionMetadataTest extends TestCase
{
    /**
     * This test verifies that our properties can be
     * accessed correctly.
     */
    public function testProperties(): void
    {
        $meta = new SubscriptionMetadata('2022-05-01', 1, IntervalUnit::MONTHS, 5, 'tr_123');

        static::assertSame('2022-05-01', $meta->getStartDate());
        static::assertSame(1, $meta->getIntervalValue());
        static::assertSame(IntervalType::MONTHS, $meta->getIntervalUnit()->value);
        static::assertSame(5, $meta->getTimes());
        static::assertSame('tr_123', $meta->getTmpTransaction());
    }

    /**
     * This test verifies that the array structure
     * for the JSON in the database is correctly built
     */
    public function testArrayStructure(): void
    {
        $meta = new SubscriptionMetadata('2022-05-01', 1, IntervalUnit::MONTHS, 5, 'tr_123');

        $expected = [
            'start_date' => '2022-05-01',
            'interval_value' => 1,
            'interval_unit' => IntervalType::MONTHS,
            'times' => 5,
            'nextPossiblePaymentDate' => '',
            'tmp_transaction' => 'tr_123',
        ];
        static::assertSame($expected, $meta->toArray());
    }

    /**
     * This test verifies that our temporary transaction ID
     * is skipped if its empty. This is really only a temporary data object,
     * that should not even exist in the JSON if empty.
     */
    public function testSkipTmpTransactionInArrayStructure(): void
    {
        $meta = new SubscriptionMetadata('', 1, IntervalUnit::MONTHS, 0, '');

        static::assertArrayNotHasKey('tmp_transaction', $meta->toArray());
    }

    /**
     * This test verifies that our property can be used correclty.
     */
    public function testTmpTransactionId(): void
    {
        $meta = new SubscriptionMetadata('2022-05-01', 1, IntervalUnit::MONTHS, 0, 'tr_123');

        $meta->setTmpTransaction('tr_xyz');

        static::assertSame('tr_xyz', $meta->getTmpTransaction());
    }
}
