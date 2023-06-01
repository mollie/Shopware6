<?php

namespace MolliePayments\Tests\Components\Subscription\DAL\Subscription\Struct;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\IntervalType;
use PHPUnit\Framework\TestCase;

class IntervalTypeTest extends TestCase
{
    /**
     * This test verifies that the value of our constant is correct.
     *
     * @return void
     */
    public function test_DAYS(): void
    {
        static::assertSame('days', IntervalType::DAYS);
    }

    /**
     * This test verifies that the value of our constant is correct.
     *
     * @return void
     */
    public function test_WEEKS(): void
    {
        static::assertSame('weeks', IntervalType::WEEKS);
    }

    /**
     * This test verifies that the value of our constant is correct.
     *
     * @return void
     */
    public function test_MONTHS(): void
    {
        static::assertSame('months', IntervalType::MONTHS);
    }
}
