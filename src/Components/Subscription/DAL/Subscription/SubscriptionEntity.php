<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription;


use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\MollieLiveData;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\SubscriptionMetadata;
use Shopware\Core\Checkout\Customer\CustomerEntity;
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
     * @var CustomerEntity
     */
    protected $customer;

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
     * @var int
     */
    protected $quantity;

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
     * @var array<mixed>|null
     */
    protected $metadata;

    /**
     * @var \DateTimeInterface|null
     */
    protected $lastRemindedAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $nextPaymentAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $canceledAt;

    /**
     * @var string
     */
    protected $mollieStatus;


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
        $data = ($this->metadata !== null) ? $this->metadata : [];

        return SubscriptionMetadata::fromArray($data);
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
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
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

    /**
     * @return \DateTimeInterface|null
     */
    public function getLastRemindedAt(): ?\DateTimeInterface
    {
        return $this->lastRemindedAt;
    }

    /**
     * @param \DateTimeInterface|null $lastRemindedAt
     */
    public function setLastRemindedAt(?\DateTimeInterface $lastRemindedAt): void
    {
        $this->lastRemindedAt = $lastRemindedAt;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getNextPaymentAt(): ?\DateTimeInterface
    {
        return $this->nextPaymentAt;
    }

    /**
     * @param \DateTimeInterface|null $nextPaymentAt
     */
    public function setNextPaymentAt(?\DateTimeInterface $nextPaymentAt): void
    {
        $this->nextPaymentAt = $nextPaymentAt;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getCanceledAt(): ?\DateTimeInterface
    {
        return $this->canceledAt;
    }

    /**
     * @param \DateTimeInterface|null $canceledAt
     */
    public function setCanceledAt(?\DateTimeInterface $canceledAt): void
    {
        $this->canceledAt = $canceledAt;
    }

    /**
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return (!empty($this->mollieId));
    }

    /**
     * @return string
     */
    public function getMollieStatus(): string
    {
        return $this->mollieStatus;
    }

    /**
     * @param string $mollieStatus
     */
    public function setMollieStatus(string $mollieStatus): void
    {
        $this->mollieStatus = $mollieStatus;
    }

    /**
     * @return CustomerEntity
     */
    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    /**
     * @param CustomerEntity $customer
     */
    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

}
