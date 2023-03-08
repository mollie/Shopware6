<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\MollieLiveData;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\MollieStatus;
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
    protected $status;

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
     * @var ?string
     */
    protected $billingAddressId;

    /**
     * @var ?string
     */
    protected $shippingAddressId;

    /**
     * @var null|array<mixed>
     */
    protected $metadata;

    /**
     * @var string
     */
    protected $mandateId;

    /**
     * @var null|\DateTimeInterface
     */
    protected $lastRemindedAt;

    /**
     * @var null|\DateTimeInterface
     */
    protected $nextPaymentAt;

    /**
     * @var null|\DateTimeInterface
     */
    protected $canceledAt;

    # --------------------------------------------------------------------------------
    # manually loaded data

    /**
     * @var null|\DateTimeInterface
     */
    protected $cancelUntil;

    # --------------------------------------------------------------------------------
    # loaded entities

    /**
     * @var SubscriptionAddressCollection
     */
    protected $addresses;

    /**
     * @var null|SubscriptionAddressEntity
     */
    protected $billingAddress;

    /**
     * @var null|SubscriptionAddressEntity
     */
    protected $shippingAddress;

    /**
     * @var SubscriptionHistoryCollection
     */
    protected $historyEntries;

    # --------------------------------------------------------------------------------

    /**
     * @param SubscriptionMetadata $metadata
     * @return void
     */
    public function setMetadata(SubscriptionMetadata $metadata): void
    {
        $this->metadata = $metadata->toArray();
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
    public function getStatus(): string
    {
        return (string)$this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
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
    public function getMandateId(): string
    {
        return (string)$this->mandateId;
    }

    /**
     * @param string $mandateId
     */
    public function setMandateId(string $mandateId): void
    {
        $this->mandateId = $mandateId;
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
     * @return null|\DateTimeInterface
     */
    public function getLastRemindedAt(): ?\DateTimeInterface
    {
        return $this->lastRemindedAt;
    }

    /**
     * @param null|\DateTimeInterface $lastRemindedAt
     */
    public function setLastRemindedAt(?\DateTimeInterface $lastRemindedAt): void
    {
        $this->lastRemindedAt = $lastRemindedAt;
    }

    /**
     * @return null|\DateTimeInterface
     */
    public function getNextPaymentAt(): ?\DateTimeInterface
    {
        return $this->nextPaymentAt;
    }

    /**
     * @param null|\DateTimeInterface $nextPaymentAt
     */
    public function setNextPaymentAt(?\DateTimeInterface $nextPaymentAt): void
    {
        $this->nextPaymentAt = $nextPaymentAt;
    }

    /**
     * @return null|\DateTimeInterface
     */
    public function getCanceledAt(): ?\DateTimeInterface
    {
        return $this->canceledAt;
    }

    /**
     * @param null|\DateTimeInterface $canceledAt
     */
    public function setCanceledAt(?\DateTimeInterface $canceledAt): void
    {
        $this->canceledAt = $canceledAt;
    }

    /**
     * @return null|SubscriptionAddressEntity
     */
    public function getBillingAddress(): ?SubscriptionAddressEntity
    {
        return $this->billingAddress;
    }

    /**
     * @param null|SubscriptionAddressEntity $billingAddress
     */
    public function setBillingAddress(?SubscriptionAddressEntity $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }

    /**
     * @return null|SubscriptionAddressEntity
     */
    public function getShippingAddress(): ?SubscriptionAddressEntity
    {
        return $this->shippingAddress;
    }

    /**
     * @param null|SubscriptionAddressEntity $shippingAddress
     */
    public function setShippingAddress(?SubscriptionAddressEntity $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    /**
     * @return null|string
     */
    public function getBillingAddressId(): ?string
    {
        return $this->billingAddressId;
    }

    /**
     * @param null|string $billingAddressId
     */
    public function setBillingAddressId(?string $billingAddressId): void
    {
        $this->billingAddressId = $billingAddressId;
    }

    /**
     * @return null|string
     */
    public function getShippingAddressId(): ?string
    {
        return $this->shippingAddressId;
    }

    /**
     * @param null|string $shippingAddressId
     */
    public function setShippingAddressId(?string $shippingAddressId): void
    {
        $this->shippingAddressId = $shippingAddressId;
    }

    /**
     * @return bool
     */
    public function isConfirmed(): bool
    {
        return (!empty($this->mollieId));
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        if ($this->getStatus() === '') {
            # we might not have data somehow
            # we treat this as "active" as long as we don't have a cancelled date
            return ($this->canceledAt === null);
        }

        return ($this->getStatus() === SubscriptionStatus::ACTIVE || $this->getStatus() === SubscriptionStatus::RESUMED);
    }

    /**
     * @return bool
     */
    public function isPaused(): bool
    {
        return ($this->getStatus() === SubscriptionStatus::PAUSED);
    }

    /**
     * @return bool
     */
    public function isSkipped(): bool
    {
        return ($this->getStatus() === SubscriptionStatus::SKIPPED);
    }

    /**
     * @return bool
     */
    public function isRenewingAllowed(): bool
    {
        $status = $this->getStatus();

        if ($status === SubscriptionStatus::ACTIVE) {
            return true;
        }

        if ($status === SubscriptionStatus::COMPLETED) {
            return true;
        }

        if ($status === SubscriptionStatus::RESUMED) {
            return true;
        }

        if ($status === SubscriptionStatus::SKIPPED) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isResumeAllowed(): bool
    {
        if ($this->getStatus() === SubscriptionStatus::PAUSED) {
            return true;
        }

        if ($this->getStatus() === SubscriptionStatus::CANCELED) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isUpdatePaymentAllowed(): bool
    {
        return $this->isActive();
    }

    /**
     * @return bool
     */
    public function isCancellationAllowed(): bool
    {
        return ($this->getStatus() !== SubscriptionStatus::CANCELED && $this->status !== SubscriptionStatus::PENDING);
    }

    /**
     * @return bool
     */
    public function isSkipAllowed(): bool
    {
        if ($this->getStatus() === SubscriptionStatus::ACTIVE) {
            return true;
        }

        if ($this->getStatus() === SubscriptionStatus::RESUMED) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isPauseAllowed(): bool
    {
        if ($this->getStatus() === SubscriptionStatus::ACTIVE) {
            return true;
        }

        if ($this->getStatus() === SubscriptionStatus::RESUMED) {
            return true;
        }

        return false;
    }

    # -----------------------------------------------------------------------------------------------------
    # manually loaded data

    /**
     * @param null|\DateTimeInterface $cancelUntil
     * @return void
     */
    public function setCancelUntil(?\DateTimeInterface $cancelUntil): void
    {
        $this->cancelUntil = $cancelUntil;
    }

    /**
     * @return null|\DateTimeInterface
     */
    public function getCancelUntil(): ?\DateTimeInterface
    {
        return $this->cancelUntil;
    }

    # -----------------------------------------------------------------------------------------------------

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

    /**
     * @return SubscriptionAddressCollection
     */
    public function getAddresses(): SubscriptionAddressCollection
    {
        return $this->addresses;
    }

    /**
     * @param SubscriptionAddressCollection $addresses
     */
    public function setAddresses(SubscriptionAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    /**
     * @return SubscriptionHistoryCollection
     */
    public function getHistoryEntries(): SubscriptionHistoryCollection
    {
        if ($this->historyEntries === null) {
            return new SubscriptionHistoryCollection();
        }

        return $this->historyEntries;
    }

    /**
     * @param SubscriptionHistoryCollection $historyEntries
     */
    public function setHistoryEntries(SubscriptionHistoryCollection $historyEntries): void
    {
        $this->historyEntries = $historyEntries;
    }
}
