<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class Subscription
{
    private ?\DateTimeInterface $cancelledAt = null;
    private ?\DateTimeInterface $createdAt = null;
    private ?\DateTimeInterface $nextPaymentDate = null;
    private ?int $timesRemaining = null;

    private PaymentCollection $payments;

    /**
     * @param array<mixed> $metadata
     */
    public function __construct(
        private string $id,
        private string $customerId,
        private string $mandateId,
        private SubscriptionStatus $status,
        private Interval $interval,
        private Money $amount,
        private string $description,
        private string $webhookUrl,
        private array $metadata,
        private \DateTimeInterface $startDate
    ) {
        $this->payments = new PaymentCollection();
    }

    /**
     * @param array<mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        $id = $body['id'];
        $customerId = $body['customerId'];
        $mandateId = $body['mandateId'];
        $description = $body['description'];
        $webhookUrl = $body['webhookUrl'];
        $amount = new Money((float) $body['amount']['value'], $body['amount']['currency']);
        $startDateValue = $body['startDate'] ?? '';
        $startDate = \DateTime::createFromFormat('Y-m-d', $startDateValue);
        if (! $startDate instanceof \DateTimeInterface) {
            throw new \RuntimeException('Startdate "%s" of Subscription is missing or invalid', $startDateValue);
        }
        $cancelledAt = \DateTime::createFromFormat(\DateTime::ATOM, $body['canceledAt'] ?? '');
        $createdAt = \DateTime::createFromFormat(\DateTime::ATOM, $body['createdAt'] ?? '');
        $interval = Interval::fromString($body['interval']);
        $status = SubscriptionStatus::from($body['status']);

        $nextPaymentDate = \DateTime::createFromFormat('Y-m-d', $body['nextPaymentDate'] ?? '');
        $timesRemaining = $body['timesRemaining'] ?? null;
        $metadata = $body['metadata'] ?? [];

        $subscription = new self($id, $customerId, $mandateId, $status, $interval, $amount, $description, $webhookUrl, $metadata, $startDate);

        if ($cancelledAt instanceof \DateTimeInterface) {
            $subscription->setCancelledAt($cancelledAt);
        }
        if ($createdAt instanceof \DateTimeInterface) {
            $subscription->setCreatedAt($createdAt);
        }
        if ($nextPaymentDate instanceof \DateTimeInterface) {
            $subscription->setNextPaymentDate($nextPaymentDate);
        }
        if ($timesRemaining !== null) {
            $subscription->setTimesRemaining((int) $timesRemaining);
        }

        $payments = new PaymentCollection();
        foreach ($body['_embedded']['payments'] ?? [] as $paymentBody) {
            $payments->add(Payment::createFromClientResponse($paymentBody));
        }
        $subscription->setPayments($payments);

        return $subscription;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function getNextPaymentDate(): ?\DateTimeInterface
    {
        return $this->nextPaymentDate;
    }

    public function getTimesRemaining(): ?int
    {
        return $this->timesRemaining;
    }

    /**
     * @return mixed[]
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setStatus(SubscriptionStatus $status): void
    {
        $this->status = $status;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getInterval(): Interval
    {
        return $this->interval;
    }

    public function getMandateId(): string
    {
        return $this->mandateId;
    }

    public function setMandateId(string $mandateId): void
    {
        $this->mandateId = $mandateId;
    }

    public function getPayments(): PaymentCollection
    {
        return $this->payments;
    }

    public function setPayments(PaymentCollection $payments): void
    {
        $this->payments = $payments;
    }

    public function getStartDate(): \DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getCancelledAt(): ?\DateTimeInterface
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeInterface $cancelledAt): void
    {
        $this->cancelledAt = $cancelledAt;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function isStateChangeWindowOpen(\DateTimeInterface $now, int $daysBeforeRenewal): bool
    {
        $nextPaymentDate = $this->getNextPaymentDate();
        if (! $nextPaymentDate instanceof \DateTimeInterface) {
            return true;
        }

        $latestCancellation = clone $nextPaymentDate;
        $latestCancellation = $latestCancellation->modify('-' . $daysBeforeRenewal . ' day');

        return $now <= $latestCancellation;
    }

    public function setNextPaymentDate(\DateTimeInterface $nextPaymentDate): void
    {
        $this->nextPaymentDate = $nextPaymentDate;
    }

    public function setTimesRemaining(int $timesRemaining): void
    {
        $this->timesRemaining = $timesRemaining;
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    public function skipPayment(): \DateTimeInterface
    {
        $nextPaymentDate = new \DateTime();
        if ($this->nextPaymentDate instanceof \DateTimeInterface) {
            /** @var \DateTime $nextPaymentDate */
            $nextPaymentDate = clone $this->nextPaymentDate;
        }

        return $nextPaymentDate->modify('+' . (string) $this->interval);
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        $body = [
            'amount' => $this->amount->toArray(),
            'description' => $this->description,
            'interval' => (string) $this->interval,
            'mandateId' => $this->mandateId,
            'metadata' => $this->metadata,
            'startDate' => $this->startDate->format('Y-m-d'),
            'webhookUrl' => $this->webhookUrl,
        ];

        if ($this->timesRemaining !== null) {
            $body['times'] = $this->timesRemaining;
        }

        return array_filter($body, function ($entry) {
            return $entry !== null && $entry !== '';
        });
    }
}
