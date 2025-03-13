<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryCollection;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Struct\SubscriptionMetadata;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Currency\CurrencyEntity;

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
     * @var ?CurrencyEntity
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

    // --------------------------------------------------------------------------------
    // manually loaded data

    /**
     * @var null|\DateTimeInterface
     */
    protected $cancelUntil;

    // --------------------------------------------------------------------------------
    // loaded entities

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

    /**
     * @var string
     */
    protected $currencyId;

    /**
     * @var ?CashRoundingConfig
     */
    protected $totalRounding;

    /**
     * @var ?CashRoundingConfig
     */
    protected $itemRounding;
    // --------------------------------------------------------------------------------

    public function setMetadata(SubscriptionMetadata $metadata): void
    {
        $this->metadata = $metadata->toArray();
    }

    public function getMetadata(): SubscriptionMetadata
    {
        $data = ($this->metadata !== null) ? $this->metadata : [];

        return SubscriptionMetadata::fromArray($data);
    }

    public function getCustomerId(): string
    {
        return (string) $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getMollieId(): string
    {
        return (string) $this->mollieId;
    }

    public function setMollieId(string $mollieId): void
    {
        $this->mollieId = $mollieId;
    }

    public function getMollieCustomerId(): string
    {
        return (string) $this->mollieCustomerId;
    }

    public function setMollieCustomerId(string $mollieCustomerId): void
    {
        $this->mollieCustomerId = $mollieCustomerId;
    }

    public function getStatus(): string
    {
        return (string) $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getAmount(): float
    {
        return (float) $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    /**
     * @return ?CurrencyEntity
     */
    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getProductId(): string
    {
        return (string) $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getOrderId(): string
    {
        return (string) $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getMandateId(): string
    {
        return (string) $this->mandateId;
    }

    public function setMandateId(string $mandateId): void
    {
        $this->mandateId = $mandateId;
    }

    public function getSalesChannelId(): string
    {
        return (string) $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getLastRemindedAt(): ?\DateTimeInterface
    {
        return $this->lastRemindedAt;
    }

    public function setLastRemindedAt(?\DateTimeInterface $lastRemindedAt): void
    {
        $this->lastRemindedAt = $lastRemindedAt;
    }

    public function getNextPaymentAt(): ?\DateTimeInterface
    {
        return $this->nextPaymentAt;
    }

    public function setNextPaymentAt(?\DateTimeInterface $nextPaymentAt): void
    {
        $this->nextPaymentAt = $nextPaymentAt;
    }

    public function getCanceledAt(): ?\DateTimeInterface
    {
        return $this->canceledAt;
    }

    public function setCanceledAt(?\DateTimeInterface $canceledAt): void
    {
        $this->canceledAt = $canceledAt;
    }

    public function getBillingAddress(): ?SubscriptionAddressEntity
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?SubscriptionAddressEntity $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }

    public function getShippingAddress(): ?SubscriptionAddressEntity
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?SubscriptionAddressEntity $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    public function getBillingAddressId(): ?string
    {
        return $this->billingAddressId;
    }

    public function setBillingAddressId(?string $billingAddressId): void
    {
        $this->billingAddressId = $billingAddressId;
    }

    public function getShippingAddressId(): ?string
    {
        return $this->shippingAddressId;
    }

    public function setShippingAddressId(?string $shippingAddressId): void
    {
        $this->shippingAddressId = $shippingAddressId;
    }

    public function isConfirmed(): bool
    {
        return ! empty($this->mollieId);
    }

    public function isActive(): bool
    {
        if ($this->getStatus() === '') {
            // we might not have data somehow
            // we treat this as "active" as long as we don't have a cancelled date
            return $this->canceledAt === null;
        }

        return $this->getStatus() === SubscriptionStatus::ACTIVE || $this->getStatus() === SubscriptionStatus::RESUMED;
    }

    public function isPaused(): bool
    {
        return $this->getStatus() === SubscriptionStatus::PAUSED;
    }

    public function isSkipped(): bool
    {
        return $this->getStatus() === SubscriptionStatus::SKIPPED;
    }

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

    public function isUpdatePaymentAllowed(): bool
    {
        return $this->isActive();
    }

    public function isCancellationAllowed(): bool
    {
        return $this->getStatus() !== SubscriptionStatus::CANCELED && $this->status !== SubscriptionStatus::PENDING;
    }

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

    // -----------------------------------------------------------------------------------------------------
    // manually loaded data

    public function setCancelUntil(?\DateTimeInterface $cancelUntil): void
    {
        $this->cancelUntil = $cancelUntil;
    }

    public function getCancelUntil(): ?\DateTimeInterface
    {
        return $this->cancelUntil;
    }

    // -----------------------------------------------------------------------------------------------------

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getAddresses(): SubscriptionAddressCollection
    {
        return $this->addresses;
    }

    public function setAddresses(SubscriptionAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function getHistoryEntries(): SubscriptionHistoryCollection
    {
        if ($this->historyEntries === null) {
            return new SubscriptionHistoryCollection();
        }

        return $this->historyEntries;
    }

    public function setHistoryEntries(SubscriptionHistoryCollection $historyEntries): void
    {
        $this->historyEntries = $historyEntries;
    }

    public function setCurrencyId(string $currencyId): void
    {
        $this->currencyId = $currencyId;
    }

    public function getCurrencyId(): ?string
    {
        return $this->currencyId;
    }

    public function setTotalRounding(?CashRoundingConfig $totalRounding): void
    {
        $this->totalRounding = $totalRounding;
    }

    public function getTotalRounding(): ?CashRoundingConfig
    {
        return $this->totalRounding;
    }

    public function getItemRounding(): ?CashRoundingConfig
    {
        return $this->itemRounding;
    }

    public function setItemRounding(?CashRoundingConfig $itemRounding): void
    {
        $this->itemRounding = $itemRounding;
    }
}
