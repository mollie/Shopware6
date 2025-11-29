<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\Subscription\Services\SubscriptionReminder;

use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionReminder\ReminderValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class ReminderValidatorTest extends TestCase
{
    /**
     * @var ReminderValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new ReminderValidator();
    }

    /**
     * We have not reached the 2 days range where our
     * reminder would start. So no reminder for here
     */
    #[Group('subscriptions')]
    #[TestWith(['2021-12-31'])]
    #[TestWith([''])]
    public function testRemindDateInFuture(string $lastReminded): void
    {
        if ($lastReminded !== '') {
            $lastReminded = new \DateTime($lastReminded);
        } else {
            $lastReminded = null;
        }

        $shouldRemind = $this->validator->shouldRemind(
            new \DateTime('2022-02-01'),
            new \DateTime('2022-01-15'),
            2,
            $lastReminded
        );

        $this->assertEquals(false, $shouldRemind);
    }

    /**
     * We have reached the first of the 5 days prior to
     * renewal where we want to start the renewal
     */
    #[Group('subscriptions')]
    #[TestWith(['2021-12-31'])]
    #[TestWith([''])]
    public function testRemindNow(string $lastReminded): void
    {
        if ($lastReminded !== '') {
            $lastReminded = new \DateTime($lastReminded);
        } else {
            $lastReminded = null;
        }

        $shouldRemind = $this->validator->shouldRemind(
            new \DateTime('2022-02-01'),
            new \DateTime('2022-01-27'),
            5,
            $lastReminded
        );

        $this->assertEquals(true, $shouldRemind);
    }

    /**
     * We are within the 5 days range to renewal, but our last
     * reminder has already been sent today.
     */
    #[Group('subscriptions')]
    public function testNoReminderIfAlreadyReminded(): void
    {
        $shouldRemind = $this->validator->shouldRemind(
            new \DateTime('2022-02-01'),
            new \DateTime('2022-01-27'),
            5,
            new \DateTime('2022-01-27')
        );

        $this->assertEquals(false, $shouldRemind);
    }

    /**
     * We have not yet sent the reminder, but we have already
     * reached the actual renewal date. A reminder would be
     * embarassing and frustrating now.
     */
    #[TestWith(['2021-12-27'])]
    #[TestWith([''])]
    #[Group('subscriptions')]
    public function testNoReminderOnRenewalDay(string $lastReminded): void
    {
        if ($lastReminded !== '') {
            $lastReminded = new \DateTime($lastReminded);
        } else {
            $lastReminded = null;
        }

        $shouldRemind = $this->validator->shouldRemind(
            new \DateTime('2022-02-01'),
            new \DateTime('2022-02-01'),
            5,
            $lastReminded
        );

        $this->assertEquals(false, $shouldRemind);
    }

    /**
     * This test verifies that we do not get reminded
     * if we don't even have an upcoming renewal.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testNoNextRenewalLeadsToNoReminder()
    {
        $shouldRemind = $this->validator->shouldRemind(
            null,
            new \DateTime('2022-01-27'),
            1,
            null
        );

        $this->assertEquals(false, $shouldRemind);
    }
}
