<?php

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

    # --------------------------------------------------------------------------------
    # loaded entities

    /**
     * @var null|SubscriptionEntity
     */
    protected $subscription;


    # --------------------------------------------------------------------------------

    /**
     * @return string
     */
    public function getSubscriptionId(): string
    {
        return $this->subscriptionId;
    }

    /**
     * @param string $subscriptionId
     */
    public function setSubscriptionId(string $subscriptionId): void
    {
        $this->subscriptionId = $subscriptionId;
    }

    /**
     * @return string
     */
    public function getStatusFrom(): string
    {
        return $this->statusFrom;
    }

    /**
     * @param string $statusFrom
     */
    public function setStatusFrom(string $statusFrom): void
    {
        $this->statusFrom = $statusFrom;
    }

    /**
     * @return string
     */
    public function getStatusTo(): string
    {
        return $this->statusTo;
    }

    /**
     * @param string $statusTo
     */
    public function setStatusTo(string $statusTo): void
    {
        $this->statusTo = $statusTo;
    }

    /**
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * @param string $comment
     */
    public function setComment(string $comment): void
    {
        $this->comment = $comment;
    }

    /**
     * @return string
     */
    public function getMollieId(): string
    {
        return $this->mollieId;
    }

    /**
     * @param string $mollieId
     */
    public function setMollieId(string $mollieId): void
    {
        $this->mollieId = $mollieId;
    }

    # --------------------------------------------------------------------------------

    /**
     * @return null|SubscriptionEntity
     */
    public function getSubscription(): ?SubscriptionEntity
    {
        return $this->subscription;
    }

    /**
     * @param null|SubscriptionEntity $subscription
     */
    public function setSubscription(?SubscriptionEntity $subscription): void
    {
        $this->subscription = $subscription;
    }
}
