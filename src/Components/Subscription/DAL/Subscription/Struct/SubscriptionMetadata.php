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
     * @param array $metadata
     * @return SubscriptionMetadata
     */
    public static function fromArray(array $metadata): SubscriptionMetadata
    {
        $startDate = (count($metadata) > 0) ? (string)$metadata['start_date'] : '';
        $intervalValue = (count($metadata) > 0) ? (int)$metadata['interval_value'] : 0;
        $intervalUnit = (count($metadata) > 0) ? (string)$metadata['interval_unit'] : '';
        $times = (count($metadata) > 0) ? $metadata['times'] : null;

        return new SubscriptionMetadata(
            $startDate,
            $intervalValue,
            $intervalUnit,
            $times
        );
    }

    /**
     * @return array
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