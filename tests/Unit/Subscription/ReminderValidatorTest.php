<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Subscription\ReminderValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReminderValidator::class)]
final class ReminderValidatorTest extends TestCase
{
    private ReminderValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ReminderValidator();
    }

    /**
     * Today is outside the reminder window.
     */
    #[Group('subscriptions')]
    #[TestWith(['2021-12-31'])]
    #[TestWith([''])]
    public function testRemindDateInFuture(string $lastReminded): void
    {
        $shouldRemind = $this->validator->shouldRemind(
            new \DateTimeImmutable('2022-02-01'),
            new \DateTimeImmutable('2022-01-15'),
            2,
            $this->parseDate($lastReminded)
        );

        $this->assertFalse($shouldRemind);
    }

    /**
     * Today is the first day of the reminder window.
     */
    #[Group('subscriptions')]
    #[TestWith(['2021-12-31'])]
    #[TestWith([''])]
    public function testRemindNow(string $lastReminded): void
    {
        $shouldRemind = $this->validator->shouldRemind(
            new \DateTimeImmutable('2022-02-01'),
            new \DateTimeImmutable('2022-01-27'),
            5,
            $this->parseDate($lastReminded)
        );

        $this->assertTrue($shouldRemind);
    }

    /**
     * We are inside the reminder window but already sent the reminder today.
     */
    #[Group('subscriptions')]
    public function testNoReminderIfAlreadyReminded(): void
    {
        $shouldRemind = $this->validator->shouldRemind(
            new \DateTimeImmutable('2022-02-01'),
            new \DateTimeImmutable('2022-01-27'),
            5,
            new \DateTimeImmutable('2022-01-27')
        );

        $this->assertFalse($shouldRemind);
    }

    /**
     * Today equals the renewal date — sending a reminder now would be misleading.
     */
    #[Group('subscriptions')]
    #[TestWith(['2021-12-27'])]
    #[TestWith([''])]
    public function testNoReminderOnRenewalDay(string $lastReminded): void
    {
        $shouldRemind = $this->validator->shouldRemind(
            new \DateTimeImmutable('2022-02-01'),
            new \DateTimeImmutable('2022-02-01'),
            5,
            $this->parseDate($lastReminded)
        );

        $this->assertFalse($shouldRemind);
    }

    #[Group('subscriptions')]
    public function testNoNextRenewalLeadsToNoReminder(): void
    {
        $shouldRemind = $this->validator->shouldRemind(
            null,
            new \DateTimeImmutable('2022-01-27'),
            1,
            null
        );

        $this->assertFalse($shouldRemind);
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        return $value === '' ? null : new \DateTimeImmutable($value);
    }
}
