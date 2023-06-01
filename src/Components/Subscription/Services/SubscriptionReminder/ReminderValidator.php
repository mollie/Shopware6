<?php

namespace Kiener\MolliePayments\Components\Subscription\Services\SubscriptionReminder;

use DateTime;
use DateTimeInterface;

class ReminderValidator
{
    /**
     * @param null|DateTimeInterface $nextRenewal
     * @param DateTimeInterface $today
     * @param int $daysDiff
     * @param null|DateTimeInterface $lastReminded
     * @throws \Exception
     * @return bool
     */
    public function shouldRemind(?DateTimeInterface $nextRenewal, DateTimeInterface $today, int $daysDiff, ?DateTimeInterface $lastReminded): bool
    {
        if ($nextRenewal === null) {
            return false;
        }


        $nextReminderDate = new DateTime($nextRenewal->format('Y-m-d H:i:s'));
        $nextReminderDate->modify("-" . $daysDiff . " day");

        $nextRenewal = new DateTime($nextRenewal->format('Y-m-d H:i:s'));

        if ($nextRenewal <= $today) {
            # either the renewal is in the past
            # or on the same day
            # in that case NO reminder
            return false;
        }

        if ($today < $nextReminderDate) {
            # it's not yet time to send the reminder
            return false;
        }

        if ($lastReminded === null) {
            # it's our first reminder :)
            return true;
        }

        if ($lastReminded >= $nextReminderDate) {
            # already reminded within the range
            return false;
        }

        return true;
    }
}
