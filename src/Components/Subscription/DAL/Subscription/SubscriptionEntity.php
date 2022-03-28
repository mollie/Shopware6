<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription;


use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\SubscriptionMetadata;
use Monolog\DateTimeImmutable;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SubscriptionEntity extends Entity
{

    use EntityIdTrait;


    /**
     * @var string
     */
    protected $customerId;

    /**
     * @var string
     */
    protected $mollieId;

    /**
     * @var string
     */
    protected $mollieCustomerId;

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
    protected $currency;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var array|null
     */
    protected $metadata;


    /**
     * @param string $mollieCustomerId
     * @param string $mollieSubscriptionId
     * @return void
     */
    public function setMollieData(string $mollieCustomerId, string $mollieSubscriptionId): void
    {
        $this->mollieCustomerId = $mollieCustomerId;
        $this->mollieId = $mollieSubscriptionId;

        $this->setMetadata('', 0, '', null);
    }

    /**
     * @param string $startDate
     * @param int $interval
     * @param string $intervalUnit
     * @param int|null $times
     */
    public function setMetadata(string $startDate, int $interval, string $intervalUnit, ?int $times): void
    {
        $meta = new SubscriptionMetadata($startDate, $interval, $intervalUnit, $times);

        $this->metadata = $meta->toArray();
    }

    /**
     * @return SubscriptionMetadata
     */
    public function getMetadata(): SubscriptionMetadata
    {
        return SubscriptionMetadata::fromArray($this->metadata);
    }

    /**
     * @return string
     */
    public function getCustomerId(): string
    {
        return (string)$this->customerId;
    }

    /**
     * @param string $customerId
     */
    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return string
     */
    public function getMollieId(): string
    {
        return (string)$this->mollieId;
    }

    /**
     * @param string $mollieId
     */
    public function setMollieId(string $mollieId): void
    {
        $this->mollieId = $mollieId;
    }

    /**
     * @return string
     */
    public function getMollieCustomerId(): string
    {
        return (string)$this->mollieCustomerId;
    }

    /**
     * @param string $mollieCustomerId
     */
    public function setMollieCustomerId(string $mollieCustomerId): void
    {
        $this->mollieCustomerId = $mollieCustomerId;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return (float)$this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return (string)$this->currency;
    }

    /**
     * @param string $currency
     */
    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @return string
     */
    public function getProductId(): string
    {
        return (string)$this->productId;
    }

    /**
     * @param string $productId
     */
    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return (string)$this->orderId;
    }

    /**
     * @param string $orderId
     */
    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * @return string
     */
    public function getSalesChannelId(): string
    {
        return (string)$this->salesChannelId;
    }

    /**
     * @param string $salesChannelId
     */
    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

}
