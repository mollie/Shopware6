<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct;

class SubscriptionMetadata
{
    /**
     * @var string
     */
    private $startDate;

    /**
     * @var int
     */
    private $interval;

    /**
     * @var string
     */
    private $intervalUnit;

    /**
     * Total number of charges for the subscription to complete.
     * Leave empty for an ongoing subscription.
     *
     * @var null|int
     */
    private $times;

    /**
     * @var string
     */
    private $tmpTransaction;

    public function __construct(string $startDate, int $interval, string $intervalUnit, ?int $times, string $tmpTransactionId)
    {
        $this->startDate = $startDate;
        $this->interval = $interval;
        $this->intervalUnit = $intervalUnit;
        $this->times = $times;
        $this->tmpTransaction = $tmpTransactionId;
    }

    /**
     * @param array<mixed> $metadata
     */
    public static function fromArray(array $metadata): SubscriptionMetadata
    {
        $startDate = (array_key_exists('start_date', $metadata)) ? (string) $metadata['start_date'] : '';
        $intervalValue = (array_key_exists('interval_value', $metadata)) ? (int) $metadata['interval_value'] : 0;
        $intervalUnit = (array_key_exists('interval_unit', $metadata)) ? (string) $metadata['interval_unit'] : '';
        $times = $metadata['times'] ?? null;
        $tmpTransactionId = (array_key_exists('tmp_transaction', $metadata)) ? (string) $metadata['tmp_transaction'] : '';

        return new SubscriptionMetadata(
            $startDate,
            $intervalValue,
            $intervalUnit,
            $times,
            $tmpTransactionId
        );
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $data = [
            'start_date' => $this->startDate,
            'interval_value' => $this->interval,
            'interval_unit' => $this->intervalUnit,
            'times' => $this->times,
        ];

        if (! empty($this->tmpTransaction)) {
            $data['tmp_transaction'] = $this->tmpTransaction;
        }

        return $data;
    }

    public function getStartDate(): string
    {
        return $this->startDate;
    }

    public function getInterval(): int
    {
        return $this->interval;
    }

    public function getIntervalUnit(): string
    {
        return $this->intervalUnit;
    }

    public function getTimes(): ?int
    {
        return $this->times;
    }

    public function getTmpTransaction(): string
    {
        return $this->tmpTransaction;
    }

    public function setTmpTransaction(string $tmpTransaction): void
    {
        $this->tmpTransaction = $tmpTransaction;
    }
}
