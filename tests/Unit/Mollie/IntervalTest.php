<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Interval::class)]
final class IntervalTest extends TestCase
{
    /**
     * @return \Generator<string, array{int, IntervalUnit, string}>
     */
    public static function intervals(): \Generator
    {
        yield 'a single month is written in the singular' => [1, IntervalUnit::MONTHS, '1 month'];
        yield 'a single week is written in the singular' => [1, IntervalUnit::WEEKS, '1 week'];
        yield 'a single day is written in the singular' => [1, IntervalUnit::DAYS, '1 day'];
        yield 'several months are written in the plural' => [3, IntervalUnit::MONTHS, '3 months'];
        yield 'several weeks are written in the plural' => [2, IntervalUnit::WEEKS, '2 weeks'];
        yield 'several days are written in the plural' => [14, IntervalUnit::DAYS, '14 days'];
    }

    #[DataProvider('intervals')]
    public function testTheIntervalIsWrittenTheWayMollieExpectsIt(int $value, IntervalUnit $unit, string $expected): void
    {
        $this->assertSame($expected, (string) new Interval($value, $unit));
    }

    /**
     * @return \Generator<string, array{string, int, IntervalUnit}>
     */
    public static function mollieAnswers(): \Generator
    {
        yield 'singular month as Mollie sends it' => ['1 month', 1, IntervalUnit::MONTHS];
        yield 'plural months as Mollie sends it' => ['3 months', 3, IntervalUnit::MONTHS];
        yield 'singular week as Mollie sends it' => ['1 week', 1, IntervalUnit::WEEKS];
        yield 'plural days as Mollie sends it' => ['14 days', 14, IntervalUnit::DAYS];
    }

    #[DataProvider('mollieAnswers')]
    public function testBothSingularAndPluralAnswersFromMollieAreUnderstood(string $answer, int $expectedValue, IntervalUnit $expectedUnit): void
    {
        $interval = Interval::fromString($answer);

        $this->assertSame($expectedValue, $interval->getIntervalValue());
        $this->assertSame($expectedUnit, $interval->getIntervalUnit());
    }

    #[DataProvider('mollieAnswers')]
    public function testAnIntervalSurvivesTheRoundTripThroughMollie(string $answer): void
    {
        $this->assertSame($answer, (string) Interval::fromString($answer));
    }
}
