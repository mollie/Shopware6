<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Subscription::class)]
final class SubscriptionTest extends TestCase
{
    public function testASubscriptionIsBuiltFromWhatMollieSends(): void
    {
        $subscription = Subscription::createFromClientResponse($this->mollieBody());

        $this->assertSame('sub_1', $subscription->getId());
        $this->assertSame('cst_1', $subscription->getCustomerId());
        $this->assertSame('mdt_1', $subscription->getMandateId());
        $this->assertSame('Monthly box', $subscription->getDescription());
        $this->assertSame(SubscriptionStatus::ACTIVE, $subscription->getStatus());
        $this->assertSame(19.99, $subscription->getAmount()->getValue());
        $this->assertSame('2026-09-01', $subscription->getStartDate()->format('Y-m-d'));
    }

    public function testAnUncancelledSubscriptionCarriesNoCancellationDate(): void
    {
        $subscription = Subscription::createFromClientResponse($this->mollieBody());

        $this->assertNull($subscription->getCancelledAt());
    }

    public function testACancelledSubscriptionKeepsTheDateMollieReported(): void
    {
        $subscription = Subscription::createFromClientResponse($this->mollieBody(['canceledAt' => '2026-08-20T09:00:00+00:00']));

        $this->assertSame('2026-08-20', $subscription->getCancelledAt()?->format('Y-m-d'));
    }

    public function testAnInvalidStartDateIsReportedWithTheValueThatWasReceived(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Startdate "not-a-date" of Subscription is missing or invalid');

        Subscription::createFromClientResponse($this->mollieBody(['startDate' => 'not-a-date']));
    }

    public function testAMissingStartDateIsReportedAsWell(): void
    {
        $body = $this->mollieBody();
        unset($body['startDate']);

        $this->expectException(\RuntimeException::class);

        Subscription::createFromClientResponse($body);
    }

    /**
     * @return \Generator<string, array{string, int, bool}>
     */
    public static function stateChangeWindows(): \Generator
    {
        yield 'well before the deadline' => ['2026-08-20 10:00:00', 3, true];
        yield 'exactly on the deadline' => ['2026-08-29 00:00:00', 3, true];
        yield 'one second past the deadline' => ['2026-08-29 00:00:01', 3, false];
        yield 'on the renewal day itself with no notice period' => ['2026-09-01 00:00:00', 0, true];
        yield 'after the renewal' => ['2026-09-02 10:00:00', 3, false];
    }

    #[DataProvider('stateChangeWindows')]
    public function testTheCustomerMayOnlyChangeTheStateUntilTheNoticePeriodStarts(string $now, int $daysBeforeRenewal, bool $expectedOpen): void
    {
        $subscription = $this->subscription();
        $subscription->setNextPaymentDate(new \DateTimeImmutable('2026-09-01 00:00:00'));

        $this->assertSame($expectedOpen, $subscription->isStateChangeWindowOpen(new \DateTimeImmutable($now), $daysBeforeRenewal));
    }

    public function testASubscriptionWithoutANextPaymentDateCanAlwaysBeChanged(): void
    {
        $this->assertTrue($this->subscription()->isStateChangeWindowOpen(new \DateTimeImmutable('2099-01-01'), 3));
    }

    public function testSkippingAPaymentMovesTheNextChargeByOneInterval(): void
    {
        $subscription = $this->subscription();
        $subscription->setNextPaymentDate(new \DateTimeImmutable('2026-09-01'));

        $this->assertSame('2026-10-01', $subscription->skipPayment()->format('Y-m-d'));
    }

    public function testSkippingDoesNotChangeTheSubscriptionItself(): void
    {
        $subscription = $this->subscription();
        $subscription->setNextPaymentDate(new \DateTimeImmutable('2026-09-01'));

        $subscription->skipPayment();

        $this->assertSame('2026-09-01', $subscription->getNextPaymentDate()?->format('Y-m-d'));
    }

    public function testThePayloadCarriesEverythingMollieNeedsForAnUpdate(): void
    {
        $body = $this->subscription()->toArray();

        $this->assertSame(['value' => '19.99', 'currency' => 'EUR'], $body['amount']);
        $this->assertSame('Monthly box', $body['description']);
        $this->assertSame('1 month', $body['interval']);
        $this->assertSame('mdt_1', $body['mandateId']);
        $this->assertSame('2026-09-01', $body['startDate']);
    }

    public function testAnOngoingSubscriptionSendsNoChargeLimit(): void
    {
        $this->assertArrayNotHasKey('times', $this->subscription()->toArray());
    }

    public function testALimitedSubscriptionSendsItsRemainingCharges(): void
    {
        $subscription = $this->subscription();
        $subscription->setTimesRemaining(6);

        $this->assertSame(6, $subscription->toArray()['times']);
    }

    public function testFieldsWithoutAValueAreLeftOutSoMollieDoesNotClearThem(): void
    {
        $subscription = new Subscription(
            'sub_1',
            'cst_1',
            '',
            SubscriptionStatus::ACTIVE,
            new Interval(1, IntervalUnit::MONTHS),
            new Money(19.99, 'EUR'),
            'Monthly box',
            '',
            [],
            new \DateTimeImmutable('2026-09-01')
        );

        $body = $subscription->toArray();

        $this->assertArrayNotHasKey('mandateId', $body);
        $this->assertArrayNotHasKey('webhookUrl', $body);
    }

    private function subscription(): Subscription
    {
        return new Subscription(
            'sub_1',
            'cst_1',
            'mdt_1',
            SubscriptionStatus::ACTIVE,
            new Interval(1, IntervalUnit::MONTHS),
            new Money(19.99, 'EUR'),
            'Monthly box',
            'https://shop.test/webhook',
            [],
            new \DateTimeImmutable('2026-09-01')
        );
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function mollieBody(array $overrides = []): array
    {
        return array_merge([
            'id' => 'sub_1',
            'customerId' => 'cst_1',
            'mandateId' => 'mdt_1',
            'description' => 'Monthly box',
            'webhookUrl' => 'https://shop.test/webhook',
            'amount' => ['value' => '19.99', 'currency' => 'EUR'],
            'startDate' => '2026-09-01',
            'createdAt' => '2026-08-01T10:00:00+00:00',
            'interval' => '1 month',
            'status' => SubscriptionStatus::ACTIVE->value,
        ], $overrides);
    }
}
