<?php

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
     * @var int|null
     */
    private $times;


    /**
     * @param string $startDate
     * @param int $interval
     * @param string $intervalUnit
     * @param int|null $times
     */
    public function __construct(string $startDate, int $interval, string $intervalUnit, ?int $times)
    {
        $this->startDate = $startDate;
        $this->interval = $interval;
        $this->intervalUnit = $intervalUnit;
        $this->times = $times;
    }

    /**
     * @param array<mixed> $metadata
     * @return SubscriptionMetadata
     */
    public static function fromArray(array $metadata): SubscriptionMetadata
    {
        $startDate = (array_key_exists('start_date', $metadata)) ? (string)$metadata['start_date'] : '';
        $intervalValue = (array_key_exists('interval_value', $metadata)) ? (int)$metadata['interval_value'] : 0;
        $intervalUnit = (array_key_exists('interval_unit', $metadata)) ? (string)$metadata['interval_unit'] : '';
        $times = (array_key_exists('times', $metadata)) ? $metadata['times'] : null;

        return new SubscriptionMetadata(
            $startDate,
            $intervalValue,
            $intervalUnit,
            $times
        );
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return [
            'start_date' => $this->startDate,
            'interval_value' => $this->interval,
            'interval_unit' => $this->intervalUnit,
            'times' => $this->times,
        ];
    }

    /**
     * @return string
     */
    public function getStartDate(): string
    {
        return $this->startDate;
    }

    /**
     * @return int
     */
    public function getInterval(): int
    {
        return $this->interval;
    }

    /**
     * @return string
     */
    public function getIntervalUnit(): string
    {
        return $this->intervalUnit;
    }

    /**
     * @return int|null
     */
    public function getTimes(): ?int
    {
        return $this->times;
    }

}