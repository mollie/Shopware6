<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

final class ReminderValidator
{
    public function shouldRemind(?\DateTimeInterface $nextRenewal, \DateTimeInterface $today, int $daysOffset, ?\DateTimeInterface $lastReminded): bool
    {
        if ($nextRenewal === null) {
            return false;
        }

        $nextReminderDate = (new \DateTimeImmutable($nextRenewal->format('Y-m-d H:i:s')))
            ->modify('-' . $daysOffset . ' day')
        ;

        $nextRenewal = new \DateTimeImmutable($nextRenewal->format('Y-m-d H:i:s'));

        if ($nextRenewal <= $today) {
            return false;
        }

        if ($today < $nextReminderDate) {
            return false;
        }

        if ($lastReminded === null) {
            return true;
        }

        if ($lastReminded >= $nextReminderDate) {
            return false;
        }

        return true;
    }
}
