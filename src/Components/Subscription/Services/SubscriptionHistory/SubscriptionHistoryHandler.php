<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\Services\SubscriptionHistory;

use Mollie\Shopware\Component\Subscription\DAL\Repository\SubscriptionRepository;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionHistory\SubscriptionHistoryEntity;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class SubscriptionHistoryHandler
{
    /**
     * @var SubscriptionRepository
     */
    private $repoSubscriptions;

    public function __construct(SubscriptionRepository $repoSubscriptions)
    {
        $this->repoSubscriptions = $repoSubscriptions;
    }

    public function markCreated(SubscriptionEntity $subscription, string $initialStatus, Context $context): void
    {
        // if it's the first one, then we have to add the
        // history for backward compatibility (in new versions it should be added immediately
        /** @var \DateTime $date */
        $date = $subscription->getCreatedAt();

        $comment = 'created';

        $this->addHistory($date, $subscription, $comment, '', $initialStatus, '', $context);
    }

    public function markConfirmed(SubscriptionEntity $subscription, string $oldStatus, string $newStatus, Context $context): void
    {
        $comment = 'confirmed';

        $this->addHistory(new \DateTime(), $subscription, $comment, $oldStatus, $newStatus, $subscription->getMollieId(), $context);
    }

    public function markBillingUpdated(SubscriptionEntity $subscription, Context $context): void
    {
        $comment = 'billing address updated';

        $this->addHistory(new \DateTime(), $subscription, $comment, '', '', $subscription->getMollieId(), $context);
    }

    public function markShipping(SubscriptionEntity $subscription, Context $context): void
    {
        $comment = 'shipping address updated';

        $this->addHistory(new \DateTime(), $subscription, $comment, '', '', $subscription->getMollieId(), $context);
    }

    public function markReminded(SubscriptionEntity $subscription, Context $context): void
    {
        $comment = 'reminded about renewal';

        $this->addHistory(new \DateTime(), $subscription, $comment, '', '', $subscription->getMollieId(), $context);
    }

    public function markRenewed(SubscriptionEntity $subscription, Context $context): void
    {
        $comment = 'renewed';

        $this->addHistory(new \DateTime(), $subscription, $comment, '', '', $subscription->getMollieId(), $context);
    }

    public function markPaymentUpdated(SubscriptionEntity $subscription, string $mandateId, Context $context): void
    {
        $comment = 'payment method updated to ' . $mandateId;

        $this->addHistory(new \DateTime(), $subscription, $comment, '', '', $subscription->getMollieId(), $context);
    }

    public function markPaused(SubscriptionEntity $subscription, string $oldStatus, string $newStatus, Context $context): void
    {
        $comment = 'paused';

        $this->addHistory(new \DateTime(), $subscription, $comment, $oldStatus, $newStatus, $subscription->getMollieId(), $context);
    }

    public function markResumed(SubscriptionEntity $subscription, string $oldStatus, string $newStatus, Context $context): void
    {
        $comment = 'resumed';

        $this->addHistory(new \DateTime(), $subscription, $comment, $oldStatus, $newStatus, $subscription->getMollieId(), $context);
    }

    public function markSkipped(SubscriptionEntity $subscription, string $oldStatus, string $newStatus, Context $context): void
    {
        $comment = 'skipped';

        $this->addHistory(new \DateTime(), $subscription, $comment, $oldStatus, $newStatus, $subscription->getMollieId(), $context);
    }

    public function markCanceled(SubscriptionEntity $subscription, string $oldStatus, string $newStatus, Context $context): void
    {
        $comment = 'canceled';

        $this->addHistory(new \DateTime(), $subscription, $comment, $oldStatus, $newStatus, $subscription->getMollieId(), $context);
    }

    private function addHistory(\DateTimeInterface $date, SubscriptionEntity $subscription, string $comment, string $oldStatus, string $newStatus, string $mollieSubId, Context $context): void
    {
        $history = new SubscriptionHistoryEntity();

        $history->setId(Uuid::randomHex());
        $history->setStatusFrom($oldStatus);
        $history->setStatusTo($newStatus);
        $history->setComment($comment);
        $history->setMollieId($mollieSubId);

        $history->setCreatedAt($date);

        $this->repoSubscriptions->addHistoryEntry($subscription->getId(), $history, $context);
    }
}
