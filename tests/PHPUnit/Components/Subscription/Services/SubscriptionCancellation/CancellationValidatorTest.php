<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\Subscription\Services\SubscriptionCancellation;

use Kiener\MolliePayments\Components\Subscription\Services\SubscriptionCancellation\CancellationValidator;
use PHPUnit\Framework\TestCase;

class CancellationValidatorTest extends TestCase
{
    /**
     * @var CancellationValidator
     */
    private $validator;

    protected function setUp(): void
    {
        $this->validator = new CancellationValidator();
    }

    /**
     * This test verifies that we can cancel the subscription
     * until its renewed if there are no restrictions and settings.
     *
     * @group subscriptions
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testNoCancellationRestrictions()
    {
        $isAllowed = $this->validator->isCancellationAllowed(
            new \DateTime('2022-07-08'),
            0,
            new \DateTime('2022-07-08'),
        );

        $this->assertEquals(true, $isAllowed);
    }

    /**
     * This test verifies that a cancellation on the last possible
     * day is indeed valid and working for the customer.
     *
     * @group subscriptions
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testNoCancellationWithDaysInRange()
    {
        $isAllowed = $this->validator->isCancellationAllowed(
            new \DateTime('2022-07-08'),
            2,
            new \DateTime('2022-07-06'),
        );

        $this->assertEquals(true, $isAllowed);
    }

    /**
     * This test verifies that it's not possible to cancel the
     * subscription as soon as the latest possible date
     * has been exceeded.
     *
     * @group subscriptions
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testNoCancellationWithDaysOutsideRange()
    {
        $isAllowed = $this->validator->isCancellationAllowed(
            new \DateTime('2022-07-08'),
            2,
            new \DateTime('2022-07-07'),
        );

        $this->assertEquals(false, $isAllowed);
    }

    /**
     * This test verifies that it's always possible to cancel
     * a subscription if there is no renewal date known.
     * This should not happen, but still we need to support it in case it does.
     *
     * @group subscriptions
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testNoRenewalDateExisting()
    {
        $isAllowed = $this->validator->isCancellationAllowed(
            null,
            2,
            new \DateTime('2022-02-8'),
        );

        $this->assertEquals(true, $isAllowed);
    }
}
