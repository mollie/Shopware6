<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\Interval;

class IntervalCalculator
{
    /**
     * @param \DateTimeInterface $baseDateTime
     * @param int $interval
     * @param string $intervalUnit
     * @return string
     */
    public function getNextIntervalDate(\DateTimeInterface $baseDateTime, int $interval, string $intervalUnit): string
    {
        # cloning does not really work
        # even though Shopware sends DateTimeImmutable, we need to ensure
        # that we don't touch the original one by its reference
        $startDateTS = $baseDateTime->getTimestamp();
        $startDate = new \DateTimeImmutable();
        $startDate = $startDate->setTimestamp($startDateTS);

        $phpModifyUnit = '';

        switch ($intervalUnit) {
            case 'day':
            case 'days':
                $phpModifyUnit = 'day';
                break;

            case 'week':
            case 'weeks':
                $phpModifyUnit = 'week';
                break;

            case 'month':
            case 'months':
                $phpModifyUnit = 'month';
                break;
        }

        $startDate = $startDate->modify('+' . $interval . ' ' . $phpModifyUnit);

        return $startDate->format('Y-m-d');
    }
}
