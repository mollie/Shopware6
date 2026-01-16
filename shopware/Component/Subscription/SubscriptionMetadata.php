<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Component\Mollie\Interval;
use Mollie\Shopware\Component\Mollie\IntervalUnit;

final class SubscriptionMetadata
{
    public function __construct(private string $startDate,
        private int $intervalValue,
        private IntervalUnit $intervalUnit,
        private int $times = 0,
        private string $tmpTransactionId = '')
    {
    }

    /**
     * @param array<mixed> $metadata
     */
    public static function fromArray(array $metadata): SubscriptionMetadata
    {
        $startDate = $metadata['start_date'] ?? '';
        $intervalValue = $metadata['interval_value'] ?? 0;
        $intervalUnit = IntervalUnit::from($metadata['interval_unit'] ?? '');
        $times = $metadata['times'] ?? 0;
        $tmpTransactionId = $metadata['tmp_transaction'] ?? '';

        return new SubscriptionMetadata(
            $startDate,
            $intervalValue,
            $intervalUnit,
            $times,
            $tmpTransactionId
        );
    }

    public function getInterval(): Interval
    {
        return new Interval($this->intervalValue, $this->intervalUnit);
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $data = [
            'start_date' => $this->startDate,
            'interval_value' => $this->intervalValue,
            'interval_unit' => $this->intervalUnit->value,
            'times' => $this->times,
        ];

        if (strlen($this->tmpTransactionId) > 0) {
            $data['tmp_transaction'] = $this->tmpTransactionId;
        }

        return $data;
    }

    public function getStartDate(): string
    {
        return $this->startDate;
    }

    public function getIntervalValue(): int
    {
        return $this->intervalValue;
    }

    public function getIntervalUnit(): IntervalUnit
    {
        return $this->intervalUnit;
    }

    public function getTimes(): int
    {
        return $this->times;
    }

    public function getTmpTransaction(): string
    {
        return $this->tmpTransactionId;
    }

    public function setTmpTransaction(string $tmpTransaction): void
    {
        $this->tmpTransactionId = $tmpTransaction;
    }
}
