<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\SessionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionStatus::class)]
final class SessionStatusTest extends TestCase
{
    #[DataProvider('completedStates')]
    public function testOnlyACompletedSessionMayBeTurnedIntoAnOrder(SessionStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isCompleted());
    }

    /**
     * @return \Generator<string, array{SessionStatus, bool}>
     */
    public static function completedStates(): \Generator
    {
        yield 'shopper finished the express flow' => [SessionStatus::COMPLETED, true];
        yield 'shopper still in the express flow' => [SessionStatus::OPEN, false];
        yield 'shopper abandoned the express flow' => [SessionStatus::EXPIRED, false];
    }

    #[DataProvider('openStates')]
    public function testOnlyAnOpenSessionIsStillWaitingForTheShopper(SessionStatus $status, bool $expected): void
    {
        $this->assertSame($expected, $status->isOpen());
    }

    /**
     * @return \Generator<string, array{SessionStatus, bool}>
     */
    public static function openStates(): \Generator
    {
        yield 'shopper still in the express flow' => [SessionStatus::OPEN, true];
        yield 'shopper finished the express flow' => [SessionStatus::COMPLETED, false];
        yield 'shopper abandoned the express flow' => [SessionStatus::EXPIRED, false];
    }
}
