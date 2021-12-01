<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Subscription\DTO;

use DateTimeImmutable;

class SubscriptionOption
{
    /**
     * @var string
     */
    private string $productId;

    /**
     * @var string
     */
    private string $salesChannelId;

    /**
     * @var array
     */
    private array $amount;

    /**
     * @var string
     */
    private string $interval;

    /**
     * @var string
     */
    private string $description;

    /**
     * @var array
     */
    private array $metadata;

    /**
     * @var string
     */
    private string $webhookUrl;

    /**
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $startDate;

    /**
     * @var int|null
     */
    private $times;


    /**
     * @param string $productId
     * @param string $salesChannelId
     * @param array $amount
     * @param string $interval
     * @param string $description
     * @param array $metadata
     * @param string $webhookUrl
     * @param DateTimeImmutable $startDate
     * @param $times
     */
    public function __construct(
        string $productId,
        string $salesChannelId,
        array $amount,
        string $interval,
        string $description,
        array $metadata,
        string $webhookUrl,
        DateTimeImmutable $startDate,
        $times = null
    ) {
        $this->productId = $productId;
        $this->salesChannelId = $salesChannelId;
        $this->amount = $amount;
        $this->interval = $interval;
        $this->description = $description;
        $this->metadata = $metadata;
        $this->webhookUrl = $webhookUrl;
        $this->startDate = $startDate;
        $this->times = $times;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return $this->productId;
    }

    /**
     * @return string
     */
    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }



    public function toArray(): array
    {
        $output = [
            'amount' => $this->amount,
            'interval' => $this->interval,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'webhookUrl' => $this->webhookUrl,
            'startDate' => $this->startDate->format('Y-m-d'),
        ];

        if ($this->times) {
            $output['times'] = $this->times;
        }

        return $output;
    }
}
