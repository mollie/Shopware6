<?php

namespace PHPUnit\Components\Subscription\Services\Builder;

use Kiener\MolliePayments\Components\Subscription\Services\Interval\IntervalCalculator;
use PHPUnit\Framework\TestCase;

class IntervalCalculatorTest extends TestCase
{
    /**
     * This test verifies that the start date of our Mollie subscription is correctly calculated.
     * This is not the date of the order, but the first calculated date in the future according
     * to the configuration of the subscription.
     * @testWith    ["day"]
     *              ["days"]
     *
     * @param string $unit
     */
    public function testIntervalWithDays(string $unit)
    {
        $builder = new IntervalCalculator();

        $orderDate = new \DateTimeImmutable('2022-07-08');

        $startDate = $builder->getNextIntervalDate($orderDate, 2, $unit);

        $this->assertEquals('2022-07-08', $orderDate->format('Y-m-d'), 'original date was modified');
        $this->assertEquals('2022-07-10', $startDate, 'calculated date is wrong');
    }

    /**
     * This test verifies that the start date of our Mollie subscription is correctly calculated.
     * This is not the date of the order, but the first calculated date in the future according
     * to the configuration of the subscription.
     * @testWith    ["week"]
     *              ["weeks"]
     *
     * @param string $unit
     */
    public function testIntervalWithWeeks(string $unit)
    {
        $builder = new IntervalCalculator();

        $orderDate = new \DateTimeImmutable('2022-08-25');

        $startDate = $builder->getNextIntervalDate($orderDate, 2, $unit);

        $this->assertEquals('2022-08-25', $orderDate->format('Y-m-d'), 'original date was modified');
        $this->assertEquals('2022-09-08', $startDate, 'calculated date is wrong');
    }

    /**
     * This test verifies that the start date of our Mollie subscription is correctly calculated.
     * This is not the date of the order, but the first calculated date in the future according
     * to the configuration of the subscription.
     * @testWith    ["month"]
     *              ["months"]
     *
     * @param string $unit
     */
    public function testIntervalWithMonths(string $unit)
    {
        $builder = new IntervalCalculator();

        $orderDate = new \DateTimeImmutable('2022-07-08');

        $startDate = $builder->getNextIntervalDate($orderDate, 2, $unit);

        $this->assertEquals('2022-07-08', $orderDate->format('Y-m-d'), 'original date was modified');
        $this->assertEquals('2022-09-08', $startDate, 'calculated date is wrong');
    }
}
