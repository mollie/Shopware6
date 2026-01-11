<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class CreateSubscription implements \JsonSerializable
{
    use JsonSerializableTrait;
    private ?int $times = null;
    private string $startDate = '';
    /**
     * @var array<mixed>
     */
    private array $metadata = [];
    private string $webhookUrl = '';
    private string $mandateId = '';

    public function __construct(
        private string $description,
        private Interval $interval,
        private Money $amount,
    ) {
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getInterval(): Interval
    {
        return $this->interval;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getTimes(): ?int
    {
        return $this->times;
    }

    public function setTimes(int $times): void
    {
        $this->times = $times;
    }

    public function getStartDate(): string
    {
        return $this->startDate;
    }

    public function setStartDate(string $startDate): void
    {
        $this->startDate = $startDate;
    }

    /**
     * @return mixed[]
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<mixed> $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    public function getMandateId(): string
    {
        return $this->mandateId;
    }

    public function setMandateId(string $mandateId): void
    {
        $this->mandateId = $mandateId;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $createSubscriptionBody = json_decode((string) json_encode($this), true);
        $createSubscriptionBody['interval'] = (string) $this->interval;

        // Remove all entries with null values
        return array_filter($createSubscriptionBody, function ($entry) {
            return $entry !== null;
        });
    }
}
