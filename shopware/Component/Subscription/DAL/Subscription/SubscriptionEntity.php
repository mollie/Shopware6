<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\DAL\Subscription;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryCollection;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\System\Currency\CurrencyEntity;

final class SubscriptionEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId = '';

    protected ?CustomerEntity $customer = null;

    protected string $mollieId = '';

    protected string $mollieCustomerId = '';

    protected string $status = '';

    protected string $description = '';

    protected float $amount = 0.0;

    protected ?CurrencyEntity $currency = null;

    protected string $orderId = '';

    protected string $orderVersionId = '';

    protected string $salesChannelId = '';

    protected ?string $billingAddressId = null;

    protected ?string $shippingAddressId = null;

    /**
     * @var null|array<mixed>
     */
    protected ?array $metadata = null;

    protected string $mandateId = '';

    protected ?\DateTimeInterface $lastRemindedAt = null;

    protected ?\DateTimeInterface $nextPaymentAt = null;

    protected ?\DateTimeInterface $canceledAt = null;

    protected ?\DateTimeInterface $cancelUntil = null;

    protected string $priceUpdateState = 'none';

    protected ?float $nextNotifiedPrice = null;

    protected ?\DateTimeInterface $notifiedAt = null;

    protected ?SubscriptionAddressCollection $addresses = null;

    protected ?SubscriptionAddressEntity $billingAddress = null;

    protected ?SubscriptionAddressEntity $shippingAddress = null;

    protected ?SubscriptionHistoryCollection $historyEntries = null;

    protected ?string $currencyId = null;

    protected ?CashRoundingConfig $totalRounding = null;

    protected ?CashRoundingConfig $itemRounding = null;

    protected ?OrderEntity $order = null;

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function setMetadata(SubscriptionMetadata $metadata): void
    {
        $this->metadata = $metadata->toArray();
    }

    public function getMetadata(): SubscriptionMetadata
    {
        return SubscriptionMetadata::fromArray($this->metadata ?? []);
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getMollieId(): string
    {
        return $this->mollieId;
    }

    public function setMollieId(string $mollieId): void
    {
        $this->mollieId = $mollieId;
    }

    public function getMollieCustomerId(): string
    {
        return $this->mollieCustomerId;
    }

    public function setMollieCustomerId(string $mollieCustomerId): void
    {
        $this->mollieCustomerId = $mollieCustomerId;
    }

    public function getStatus(): string
    {
        return $this->status;
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
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): ?CurrencyEntity
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyEntity $currency): void
    {
        $this->currency = $currency;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderVersionId(): string
    {
        return $this->orderVersionId;
    }

    public function setOrderVersionId(string $orderVersionId): void
    {
        $this->orderVersionId = $orderVersionId;
    }

    public function getMandateId(): string
    {
        return $this->mandateId;
    }

    public function setMandateId(string $mandateId): void
    {
        $this->mandateId = $mandateId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
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

    public function getPriceUpdateState(): string
    {
        return $this->priceUpdateState;
    }

    public function setPriceUpdateState(string $priceUpdateState): void
    {
        $this->priceUpdateState = $priceUpdateState;
    }

    public function getNextNotifiedPrice(): ?float
    {
        return $this->nextNotifiedPrice;
    }

    public function setNextNotifiedPrice(?float $nextNotifiedPrice): void
    {
        $this->nextNotifiedPrice = $nextNotifiedPrice;
    }

    public function getNotifiedAt(): ?\DateTimeInterface
    {
        return $this->notifiedAt;
    }

    public function setNotifiedAt(?\DateTimeInterface $notifiedAt): void
    {
        $this->notifiedAt = $notifiedAt;
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
            return $this->canceledAt === null;
        }

        return $this->getStatus() === SubscriptionStatus::ACTIVE->value || $this->getStatus() === SubscriptionStatus::RESUMED->value;
    }

    public function isPaused(): bool
    {
        return $this->getStatus() === SubscriptionStatus::PAUSED->value;
    }

    public function isSkipped(): bool
    {
        return $this->getStatus() === SubscriptionStatus::SKIPPED->value;
    }

    public function isRenewingAllowed(): bool
    {
        $status = $this->getStatus();

        if ($status === SubscriptionStatus::ACTIVE->value) {
            return true;
        }

        if ($status === SubscriptionStatus::COMPLETED->value) {
            return true;
        }

        if ($status === SubscriptionStatus::RESUMED->value) {
            return true;
        }

        if ($status === SubscriptionStatus::SKIPPED->value) {
            return true;
        }

        return false;
    }

    public function isResumeAllowed(): bool
    {
        if ($this->getStatus() === SubscriptionStatus::PAUSED->value) {
            return true;
        }

        if ($this->getStatus() === SubscriptionStatus::CANCELED->value) {
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
        return $this->getStatus() !== SubscriptionStatus::CANCELED->value && $this->status !== SubscriptionStatus::PENDING->value;
    }

    public function isSkipAllowed(): bool
    {
        if ($this->getStatus() === SubscriptionStatus::ACTIVE->value) {
            return true;
        }

        if ($this->getStatus() === SubscriptionStatus::RESUMED->value) {
            return true;
        }

        return false;
    }

    public function isPauseAllowed(): bool
    {
        if ($this->getStatus() === SubscriptionStatus::ACTIVE->value) {
            return true;
        }

        if ($this->getStatus() === SubscriptionStatus::RESUMED->value) {
            return true;
        }

        return false;
    }

    public function setCancelUntil(?\DateTimeInterface $cancelUntil): void
    {
        $this->cancelUntil = $cancelUntil;
    }

    public function getCancelUntil(): ?\DateTimeInterface
    {
        return $this->cancelUntil;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getAddresses(): SubscriptionAddressCollection
    {
        return $this->addresses ?? new SubscriptionAddressCollection();
    }

    public function setAddresses(SubscriptionAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function getHistoryEntries(): SubscriptionHistoryCollection
    {
        return $this->historyEntries ?? new SubscriptionHistoryCollection();
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
