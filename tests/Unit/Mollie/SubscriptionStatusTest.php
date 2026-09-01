<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\Action\CancelAction;
use Mollie\Shopware\Component\Subscription\Action\PauseAction;
use Mollie\Shopware\Component\Subscription\Action\SkipAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubscriptionStatus::class)]
final class SubscriptionStatusTest extends TestCase
{
    #[DataProvider('activeStates')]
    public function testOnlyRunningAndResumedSubscriptionsAreActive(SubscriptionStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isActive());
    }

    /**
     * @return \Generator<string, array{SubscriptionStatus, bool}>
     */
    public static function activeStates(): \Generator
    {
        yield 'running at mollie' => [SubscriptionStatus::ACTIVE, true];
        yield 'resumed by the customer' => [SubscriptionStatus::RESUMED, true];
        yield 'paused by the customer' => [SubscriptionStatus::PAUSED, false];
        yield 'skipped for one interval' => [SubscriptionStatus::SKIPPED, false];
        yield 'not yet confirmed' => [SubscriptionStatus::PENDING, false];
        yield 'suspended by mollie' => [SubscriptionStatus::SUSPENDED, false];
        yield 'run to its end' => [SubscriptionStatus::COMPLETED, false];
        yield 'cancelled for good' => [SubscriptionStatus::CANCELED, false];
    }

    #[DataProvider('interruptedStates')]
    public function testOnlyPausedAndSkippedSubscriptionsAreInterrupted(SubscriptionStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isInterrupted());
    }

    /**
     * @return \Generator<string, array{SubscriptionStatus, bool}>
     */
    public static function interruptedStates(): \Generator
    {
        yield 'paused by the customer' => [SubscriptionStatus::PAUSED, true];
        yield 'skipped for one interval' => [SubscriptionStatus::SKIPPED, true];
        yield 'running at mollie' => [SubscriptionStatus::ACTIVE, false];
        yield 'cancelled for good' => [SubscriptionStatus::CANCELED, false];
        yield 'pause takes effect after the renewal' => [SubscriptionStatus::PAUSED_AFTER_RENEWAL, false];
    }

    #[DataProvider('statesWithPendingAction')]
    public function testOnlyAfterRenewalStatesCarryAnActionToRunLater(SubscriptionStatus $status, ?string $expectedAction): void
    {
        $this->assertSame($expectedAction, $status->getAction());
    }

    /**
     * @return \Generator<string, array{SubscriptionStatus, null|string}>
     */
    public static function statesWithPendingAction(): \Generator
    {
        yield 'skip is due after the renewal' => [SubscriptionStatus::SKIPPED_AFTER_RENEWAL, SkipAction::getActioName()];
        yield 'pause is due after the renewal' => [SubscriptionStatus::PAUSED_AFTER_RENEWAL, PauseAction::getActioName()];
        yield 'cancel is due after the renewal' => [SubscriptionStatus::CANCELED_AFTER_RENEWAL, CancelAction::getActioName()];
        yield 'a running subscription has nothing pending' => [SubscriptionStatus::ACTIVE, null];
        yield 'an already skipped subscription has nothing pending' => [SubscriptionStatus::SKIPPED, null];
    }
}
