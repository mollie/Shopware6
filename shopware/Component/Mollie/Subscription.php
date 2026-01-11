<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class Subscription
{
    private PaymentCollection $payments;

    /**
     * @param array<mixed> $metadata
     */
    public function __construct(
        private string $id,
        private SubscriptionStatus $status,
        private \DateTimeInterface $nextPaymentDate,
        private int $timesRemaining,
        private array $metadata
    ) {
    }

    /**
     * @param array<mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        $id = $body['id'];
        $status = SubscriptionStatus::from($body['status']);
        $nextPaymentDate = \DateTime::createFromFormat('Y-m-d', $body['nextPaymentDate']);
        if (! $nextPaymentDate instanceof \DateTimeInterface) {
            throw new \RuntimeException('Next Payment Date must be a valid date. Got following date from mollie api:' . $body['nextPaymentDate']);
        }
        $timesRemaining = $body['timesRemaining'] ?? 0;
        $metadata = $body['metadata'] ?? [];

        return new self($id, $status, $nextPaymentDate, $timesRemaining, $metadata);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): SubscriptionStatus
    {
        return $this->status;
    }

    public function getNextPaymentDate(): \DateTimeInterface
    {
        return $this->nextPaymentDate;
    }

    public function getTimesRemaining(): int
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

    public function getPayments(): PaymentCollection
    {
        return $this->payments;
    }

    public function setPayments(PaymentCollection $payments): void
    {
        $this->payments = $payments;
    }

    public function setStatus(SubscriptionStatus $status): void
    {
        $this->status = $status;
    }
}
