<?php

namespace MolliePayments\Tests\Components\Subscription\DAL\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\IntervalType;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\MollieStatus;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\SubscriptionMetadata;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEvents;
use PHPUnit\Framework\TestCase;


class SubscriptionEntityTest extends TestCase
{

    /**
     * This test verifies that our metadata is correctly
     * set and returned from our entity.
     * It will require and return a custom class, but internally it's just
     * stored as a plain array structure.
     * So in the end this test verifies that the conversion works correctly.
     *
     * @return void
     */
    public function testMetadataCorrectlyConverted(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setMetadata(
            new SubscriptionMetadata(
                '2022-05-01',
                1,
                IntervalType::MONTHS,
                null,
                ''
            )
        );

        $returnedMeta = $subscription->getMetadata();

        $expected = [
            'start_date' => '2022-05-01',
            'interval_value' => 1,
            'interval_unit' => IntervalType::MONTHS,
            'times' => null,
        ];

        static::assertSame($expected, $returnedMeta->toArray());
    }

    /**
     * This test verifies that our default value
     * of the Mollie status is a correct empty string, if
     * it has not been set yet.
     *
     * @return void
     */
    public function testMollieStatusDefaultEmpty(): void
    {
        $subscription = new SubscriptionEntity();

        static::assertSame('', $subscription->getMollieStatus());
    }

    /**
     * This test verifies that our custom Mollie Status
     * can be set and returned correctly.
     *
     * @return void
     */
    public function testMollieStatus(): void
    {
        $subscription = new SubscriptionEntity();
        $subscription->setMollieStatus('active');

        static::assertSame('active', $subscription->getMollieStatus());
    }

    /**
     * This test verifies that our isConfirmed property is only returning TRUE
     * if we have a MollieID set in the entity.
     *
     * @return void
     */
    public function testIsConfirmed(): void
    {
        $subscription = new SubscriptionEntity();

        static::assertSame(false, $subscription->isConfirmed());

        $subscription->setMollieId('sub_123');

        static::assertSame(true, $subscription->isConfirmed());
    }

    /**
     * This test verifies that our isActive property is only returning TRUE
     * if we have a status "active" set in the entity.
     *
     * @return void
     */
    public function testIsActive(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setCanceledAt(null);

        # if we don't have a LIVE value
        # then we act, as if it would be active, as long as no cancelled date is set.
        # this is, because if the Mollie API is somehow not reachable, then we
        # still need to let customers change things
        static::assertSame(true, $subscription->isActive());

        # let's change the cancelled
        # and keep an empty string for the status
        $subscription->setCanceledAt(new \DateTime());
        static::assertSame(false, $subscription->isActive());

        # now just revert to the cancelled
        # and only check the mollie status
        $subscription->setCanceledAt(null);

        # set wrong status
        $subscription->setMollieStatus(MollieStatus::CANCELED);
        static::assertSame(false, $subscription->isActive());

        # now set correct status
        $subscription->setMollieStatus(MollieStatus::ACTIVE);
        static::assertSame(true, $subscription->isActive());
    }

}
