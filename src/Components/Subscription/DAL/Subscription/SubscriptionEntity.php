<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription;


use Monolog\DateTimeImmutable;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SubscriptionEntity extends Entity
{
    use EntityIdTrait;


    /**
     * @var string
     */
    protected $description;

    /**
     * @var float
     */
    protected $amount;

    /**
     * @var string
     */
    private $currencyIso;

    /**
     * @var string
     */
    protected $shopwareProductId;

    /**
     * @var string
     */
    private $startDate;

    /**
     * @var string
     */
    private $intervalValue;

    /**
     * @var string
     */
    private $intervalType;

    /**
     * @var string
     */
    private $repetitionAmount;

    /**
     * @var string
     */
    protected $originalOrderId;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var string
     */
    protected $mollieSubscriptionId;

    /**
     * @var string
     */
    protected $mollieCustomerId;


    /**
     * @param string $description
     * @param float $amount
     * @param string $currencyISO
     * @param string $shopwareProductId
     * @param string $startDate
     * @param string $intervalValue
     * @param string $intervalType
     * @param string $repetitionAmount
     * @param string $originalOrderId
     * @param string $salesChannelId
     */
    public function __construct(string $description, float $amount, string $currencyISO, string $shopwareProductId, string $startDate, string $intervalValue, string $intervalType, string $repetitionAmount, string $originalOrderId, string $salesChannelId)
    {
        $this->description = $description;
        $this->amount = $amount;
        $this->currencyIso = $currencyISO;
        $this->shopwareProductId = $shopwareProductId;
        $this->startDate = $startDate;
        $this->intervalValue = $intervalValue;
        $this->intervalType = $intervalType;
        $this->repetitionAmount = $repetitionAmount;
        $this->originalOrderId = $originalOrderId;
        $this->salesChannelId = $salesChannelId;
    }


    /**
     * @param string $mollieCustomerId
     * @param string $mollieSubscriptionId
     * @return void
     */
    public function setMollieData(string $mollieCustomerId, string $mollieSubscriptionId): void
    {
        $this->mollieCustomerId = $mollieCustomerId;
        $this->mollieSubscriptionId = $mollieSubscriptionId;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getCurrencyIso(): string
    {
        return $this->currencyIso;
    }

    /**
     * @return string
     */
    public function getShopwareProductId(): string
    {
        return $this->shopwareProductId;
    }

    /**
     * @return string
     */
    public function getStartDate(): string
    {
        return $this->startDate;
    }

    /**
     * @return string
     */
    public function getIntervalValue(): string
    {
        return $this->intervalValue;
    }

    /**
     * @return string
     */
    public function getIntervalType(): string
    {
        return $this->intervalType;
    }

    /**
     * @return string
     */
    public function getRepetitionAmount(): string
    {
        return $this->repetitionAmount;
    }

    /**
     * @return string
     */
    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    /**
     * @return string
     */
    public function getMollieCustomerId(): string
    {
        return $this->mollieCustomerId;
    }

    /**
     * @return string
     */
    public function getMollieSubscriptionId(): string
    {
        return $this->mollieSubscriptionId;
    }

    /**
     * @return string
     */
    public function getOriginalOrderId(): string
    {
        return $this->originalOrderId;
    }

}
