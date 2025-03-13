<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory;

use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class SubscriptionHistoryEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $subscriptionId;

    /**
     * @var string
     */
    protected $statusFrom;

    /**
     * @var string
     */
    protected $statusTo;

    /**
     * @var string
     */
    protected $comment;

    /**
     * @var string
     */
    protected $mollieId;

    // --------------------------------------------------------------------------------
    // loaded entities

    /**
     * @var null|SubscriptionEntity
     */
    protected $subscription;

    // --------------------------------------------------------------------------------

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

    // --------------------------------------------------------------------------------

    public function getSubscription(): ?SubscriptionEntity
    {
        return $this->subscription;
    }

    public function setSubscription(?SubscriptionEntity $subscription): void
    {
        $this->subscription = $subscription;
    }
}
