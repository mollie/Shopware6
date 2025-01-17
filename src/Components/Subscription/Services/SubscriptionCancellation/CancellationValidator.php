<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation;

use DateTime;
use DateTimeInterface;

class CancellationValidator
{
    /**
     * @param null|DateTimeInterface $nextRenewal
     * @param int $maxCancellationDays
     * @param DateTimeInterface $today
     * @throws \Exception
     * @return bool
     */
    public function isCancellationAllowed(?DateTimeInterface $nextRenewal, int $maxCancellationDays, DateTimeInterface $today): bool
    {
        if ($nextRenewal === null) {
            return true;
        }

        $latestCancellation = new DateTime($nextRenewal->format('Y-m-d H:i:s'));
        $latestCancellation->modify("-" . $maxCancellationDays . " day");

        if ($today <= $latestCancellation) {
            return true;
        }

        return false;
    }
}
