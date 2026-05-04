<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class SubscriptionHistoryEntity extends Entity
{
    use EntityIdTrait;

    protected string $subscriptionId = '';

    protected string $statusFrom = '';

    protected string $statusTo = '';

    protected string $comment = '';

    protected string $mollieId = '';

    protected ?SubscriptionEntity $subscription = null;

    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    public function setSubscriptionId(string $subscriptionId): void
    {
        $this->subscriptionId = $subscriptionId;
    }

    public function getStatusFrom(): string
    {
        return $this->statusFrom;
    }

    public function setStatusFrom(string $statusFrom): void
    {
        $this->statusFrom = $statusFrom;
    }

    public function getStatusTo(): string
    {
        return $this->statusTo;
    }

    public function setStatusTo(string $statusTo): void
    {
        $this->statusTo = $statusTo;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    public function getMollieId(): string
    {
        return $this->mollieId;
    }

    public function setMollieId(string $mollieId): void
    {
        $this->mollieId = $mollieId;
    }

    public function getSubscription(): ?SubscriptionEntity
    {
        return $this->subscription;
    }

    public function setSubscription(?SubscriptionEntity $subscription): void
    {
        $this->subscription = $subscription;
    }
}
