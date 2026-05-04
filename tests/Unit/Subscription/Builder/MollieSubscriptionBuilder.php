<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Builder;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;

final class MollieSubscriptionBuilder
{
    private string $id = 'sub_test123';
    private string $customerId = 'cst_test123';
    private string $mandateId = 'mdt_test123';
    private SubscriptionStatus $status = SubscriptionStatus::ACTIVE;
    private Interval $interval;
    private Money $amount;
    private string $description = 'Test Subscription';
    private string $webhookUrl = 'https://example.test/webhook';
    /** @var array<mixed> */
    private array $metadata = [];
    private \DateTimeInterface $startDate;
    private ?\DateTimeInterface $nextPaymentDate = null;
    private ?int $timesRemaining = null;
    private ?PaymentCollection $payments = null;

    public function __construct()
    {
        $this->interval = new Interval(1, IntervalUnit::MONTHS);
        $this->amount = new Money(10.00, 'EUR');
        $this->startDate = new \DateTimeImmutable('2026-01-01');
    }

    public static function create(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function withStatus(SubscriptionStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function withNextPaymentDate(\DateTimeInterface $date): self
    {
        $this->nextPaymentDate = $date;

        return $this;
    }

    public function withTimesRemaining(int $timesRemaining): self
    {
        $this->timesRemaining = $timesRemaining;

        return $this;
    }

    public function withInterval(int $value, IntervalUnit $unit): self
    {
        $this->interval = new Interval($value, $unit);

        return $this;
    }

    public function withPayments(PaymentCollection $payments): self
    {
        $this->payments = $payments;

        return $this;
    }

    public function build(): Subscription
    {
        $subscription = new Subscription(
            $this->id,
            $this->customerId,
            $this->mandateId,
            $this->status,
            $this->interval,
            $this->amount,
            $this->description,
            $this->webhookUrl,
            $this->metadata,
            $this->startDate
        );

        if ($this->nextPaymentDate instanceof \DateTimeInterface) {
            $subscription->setNextPaymentDate($this->nextPaymentDate);
        }

        if ($this->timesRemaining !== null) {
            $subscription->setTimesRemaining($this->timesRemaining);
        }

        if ($this->payments instanceof PaymentCollection) {
            $subscription->setPayments($this->payments);
        }

        return $subscription;
    }
}
