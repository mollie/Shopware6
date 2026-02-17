<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Components\Subscription\DAL\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\IntervalType;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\MollieStatus;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionStatus;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use PHPUnit\Framework\TestCase;

class SubscriptionEntityTest extends TestCase
{
    /**
     * This test verifies that our default value
     * of the Mollie status is a correct empty string, if
     * it has not been set yet.
     */
    public function testStatusDefaultEmpty(): void
    {
        $subscription = new SubscriptionEntity();

        static::assertSame('', $subscription->getStatus());
    }

    /**
     * This test verifies that our custom Status
     * can be set and returned correctly.
     */
    public function testStatus(): void
    {
        $subscription = new SubscriptionEntity();
        $subscription->setStatus('active');

        static::assertSame('active', $subscription->getStatus());
    }

    /**
     * This test verifies that our isConfirmed property is only returning TRUE
     * if we have a MollieID set in the entity.
     */
    public function testIsConfirmed(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setMollieId('');
        static::assertSame(false, $subscription->isConfirmed());

        $subscription->setMollieId('sub_123');
        static::assertSame(true, $subscription->isConfirmed());
    }

    /**
     * This test verifies that our isActive property is only returning TRUE
     * if we have a status "active" set in the entity.
     */
    public function testIsActive(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        $subscription->setCanceledAt(null);
        static::assertSame(true, $subscription->isActive(), 'active if status active and no canceled date');

        $subscription->setStatus(SubscriptionStatus::RESUMED);
        $subscription->setCanceledAt(null);
        static::assertSame(true, $subscription->isActive(), 'active if status resumed and no canceled date');

        $subscription->setStatus('');
        $subscription->setCanceledAt(new \DateTime());
        static::assertSame(false, $subscription->isActive(), 'not active if no status but canceled date');

        $subscription->setStatus(MollieStatus::CANCELED);
        $subscription->setCanceledAt(null);
        static::assertSame(false, $subscription->isActive(), 'canceled if status canceled but no canceled date');
    }

    /**
     * This test verifies that our isPaused works correctly.
     */
    public function testIsPaused(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        static::assertSame(false, $subscription->isPaused());

        $subscription->setStatus(SubscriptionStatus::PAUSED);
        static::assertSame(true, $subscription->isPaused());
    }

    /**
     * This test verifies that our isPaused works correctly.
     */
    public function testIsSkipped(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setStatus(SubscriptionStatus::SKIPPED);
        static::assertSame(true, $subscription->isSkipped());

        $subscription->setStatus(SubscriptionStatus::PAUSED);
        static::assertSame(false, $subscription->isSkipped());
    }

    /**
     * This test verifies that our isUpdatePaymentAllowed works correctly.
     */
    public function testIsUpdatePaymentAllowed(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        static::assertSame(true, $subscription->isUpdatePaymentAllowed());

        $subscription->setStatus(SubscriptionStatus::RESUMED);
        static::assertSame(true, $subscription->isUpdatePaymentAllowed());

        $subscription->setStatus(SubscriptionStatus::CANCELED);
        static::assertSame(false, $subscription->isUpdatePaymentAllowed());
    }

    /**
     * This test verifies that our isCancellationAllowed works correctly.
     */
    public function testIsCancellationAllowed(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        static::assertSame(true, $subscription->isCancellationAllowed());

        $subscription->setStatus(SubscriptionStatus::SKIPPED);
        static::assertSame(true, $subscription->isCancellationAllowed());

        $subscription->setStatus(SubscriptionStatus::RESUMED);
        static::assertSame(true, $subscription->isCancellationAllowed());

        $subscription->setStatus(SubscriptionStatus::CANCELED);
        static::assertSame(false, $subscription->isCancellationAllowed());

        $subscription->setStatus(SubscriptionStatus::PENDING);
        static::assertSame(false, $subscription->isCancellationAllowed());
    }

    /**
     * This test verifies that our isPauseAllowed works correctly.
     */
    public function testIsPauseAllowed(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        static::assertSame(true, $subscription->isPauseAllowed());

        $subscription->setStatus(SubscriptionStatus::RESUMED);
        static::assertSame(true, $subscription->isPauseAllowed());

        $subscription->setStatus(SubscriptionStatus::SKIPPED);
        static::assertSame(false, $subscription->isPauseAllowed());

        $subscription->setStatus(SubscriptionStatus::CANCELED);
        static::assertSame(false, $subscription->isPauseAllowed());
    }

    /**
     * This test verifies that our isRenewingAllowed works correctly.
     */
    public function testIsRenewingAllowed(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        static::assertSame(true, $subscription->isRenewingAllowed());

        $subscription->setStatus(SubscriptionStatus::COMPLETED);
        static::assertSame(true, $subscription->isRenewingAllowed());

        $subscription->setStatus(SubscriptionStatus::SKIPPED);
        static::assertSame(true, $subscription->isRenewingAllowed());

        $subscription->setStatus(SubscriptionStatus::RESUMED);
        static::assertSame(true, $subscription->isRenewingAllowed());

        $subscription->setStatus(SubscriptionStatus::PAUSED);
        static::assertSame(false, $subscription->isRenewingAllowed());

        $subscription->setStatus(SubscriptionStatus::CANCELED);
        static::assertSame(false, $subscription->isRenewingAllowed());

        $subscription->setStatus(SubscriptionStatus::PENDING);
        static::assertSame(false, $subscription->isRenewingAllowed());
    }

    /**
     * This test verifies that our isResumeAllowed works correctly.
     */
    public function testIsResumeAllowed(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setStatus(SubscriptionStatus::PAUSED);
        static::assertSame(true, $subscription->isResumeAllowed());

        $subscription->setStatus(SubscriptionStatus::CANCELED);
        static::assertSame(true, $subscription->isResumeAllowed());

        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        static::assertSame(false, $subscription->isResumeAllowed());

        $subscription->setStatus(SubscriptionStatus::SKIPPED);
        static::assertSame(false, $subscription->isResumeAllowed());
    }

    /**
     * This test verifies that our isSkipAllowed works correctly.
     */
    public function testIsSkipAllowed(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setStatus(SubscriptionStatus::ACTIVE);
        static::assertSame(true, $subscription->isSkipAllowed());

        $subscription->setStatus(SubscriptionStatus::RESUMED);
        static::assertSame(true, $subscription->isSkipAllowed());

        $subscription->setStatus(SubscriptionStatus::CANCELED);
        static::assertSame(false, $subscription->isSkipAllowed());

        $subscription->setStatus(SubscriptionStatus::SKIPPED);
        static::assertSame(false, $subscription->isSkipAllowed());
    }

    /**
     * This test verifies that our metadata is correctly
     * set and returned from our entity.
     * It will require and return a custom class, but internally it's just
     * stored as a plain array structure.
     * So in the end this test verifies that the conversion works correctly.
     */
    public function testMetadataCorrectlyConverted(): void
    {
        $subscription = new SubscriptionEntity();

        $subscription->setMetadata(
            new SubscriptionMetadata(
                '2022-05-01',
                1,
                IntervalUnit::MONTHS,
                0,
                ''
            )
        );

        $returnedMeta = $subscription->getMetadata();

        $expected = [
            'start_date' => '2022-05-01',
            'interval_value' => 1,
            'interval_unit' => IntervalType::MONTHS,
            'times' => 0,
            'nextPossiblePaymentDate' => '',
        ];

        static::assertSame($expected, $returnedMeta->toArray());
    }
}
