<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation;

use DateTime;
use DateTimeInterface;

class CancellationValidator
{
    /**
     * @throws \Exception
     */
    public function isCancellationAllowed(?DateTimeInterface $nextRenewal, int $maxCancellationDays, DateTimeInterface $today): bool
    {
        if ($nextRenewal === null) {
            return true;
        }

        $latestCancellation = new DateTime($nextRenewal->format('Y-m-d H:i:s'));
        $latestCancellation->modify('-' . $maxCancellationDays . ' day');

        return $today <= $latestCancellation;
    }
}
