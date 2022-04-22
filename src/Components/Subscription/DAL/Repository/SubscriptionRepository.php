<?php

namespace Kiener\MolliePayments\Components\Subscription\DAL\Repository;


use DateTime;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Service\ConfigService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SubscriptionRepository
{

    /**
     * @var EntityRepositoryInterface
     */
    private $repoSubscriptions;

    /**
     * @param EntityRepositoryInterface $repoSubscriptions
     */
    public function __construct(EntityRepositoryInterface $repoSubscriptions)
    {
        $this->repoSubscriptions = $repoSubscriptions;
    }


    /**
     * @return EntityRepositoryInterface
     */
    public function getRepository(): EntityRepositoryInterface
    {
        return $this->repoSubscriptions;
    }

    /**
     * @param string $id
     * @param Context $context
     * @return SubscriptionEntity
     */
    public function findById(string $id, Context $context): SubscriptionEntity
    {
        $criteria = new Criteria([$id]);

        return $this->repoSubscriptions->search($criteria, $context)->first();
    }

    /**
     * @param Context $context
     * @return EntitySearchResult<SubscriptionEntity>
     */
    public function findAll(Context $context): EntitySearchResult
    {
        $criteria = new Criteria();

        return $this->repoSubscriptions->search($criteria, $context);
    }

    /**
     * @param int $daysOffset
     * @param Context $context
     * @return EntitySearchResult<SubscriptionEntity>
     * @throws \Exception
     */
    public function findByReminderRangeReached(int $daysOffset, Context $context): EntitySearchResult
    {
        # let's use our current date and remove the
        # provided number of offset days.
        # the final result is the days in advance
        $interval = new \DateInterval('P' . $daysOffset . 'D');
        $prepaymentDate = (new \DateTimeImmutable)->sub($interval);

        $today = (new \DateTimeImmutable);

        $criteria = new Criteria();

        # subscription is not canceled
        $criteria->addFilter(new EqualsFilter('canceledAt', null));

        # payment has to be in the future
        $criteria->addFilter(new RangeFilter('nextPaymentAt', ['gte' => $today->format('Y-m-d H:i:s')]));

        return $this->repoSubscriptions->search($criteria, $context);
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return EntitySearchResult<SubscriptionEntity>
     */
    public function findPendingSubscriptions(string $orderId, Context $context): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addFilter(new EqualsFilter('mollieId', null));

        return $this->repoSubscriptions->search($criteria, $context);
    }

    /**
     * @param SubscriptionEntity $subscription
     * @param Context $context
     */
    public function insertSubscription(SubscriptionEntity $subscription, Context $context): void
    {
        $this->repoSubscriptions->create([
            [
                'id' => $subscription->getId(),
                'customerId' => $subscription->getCustomerId(),
                # ----------------------------------------------------------
                # new subscriptions have NULL for the external
                # mollie data. this means the payment is not confirmed yet.
                'mollieCustomerId' => null,
                'mollieSubscriptionId' => null,
                'lastRemindedAt' => null,
                'canceledAt' => null,
                # ----------------------------------------------------------
                'description' => $subscription->getDescription(),
                'amount' => $subscription->getAmount(),
                'quantity' => $subscription->getQuantity(),
                'currency' => $subscription->getCurrency(),
                'metadata' => $subscription->getMetadata()->toArray(),
                'productId' => $subscription->getProductId(),
                'orderId' => $subscription->getOrderId(),
                'salesChannelId' => $subscription->getSalesChannelId(),
            ]
        ],
            $context
        );
    }

    /**
     * @param string $id
     * @param string $mollieSubscriptionId
     * @param string $mollieCustomerId
     * @param string $nextPaymentDate
     * @param Context $context
     */
    public function confirmSubscription(string $id, string $mollieSubscriptionId, string $mollieCustomerId, string $nextPaymentDate, Context $context): void
    {
        $this->repoSubscriptions->update([
            [
                'id' => $id,
                'mollieId' => $mollieSubscriptionId,
                'mollieCustomerId' => $mollieCustomerId,
                'nextPaymentAt' => $nextPaymentDate,
            ]
        ],
            $context
        );
    }

    /**
     * @param string $id
     * @param string $nextPaymentDate
     * @param Context $context
     * @return void
     */
    public function updateNextPaymentAt(string $id, string $nextPaymentDate, Context $context): void
    {
        $this->repoSubscriptions->update([
            [
                'id' => $id,
                'nextPaymentAt' => $nextPaymentDate,
            ]
        ],
            $context
        );
    }

    /**
     * @param string $id
     * @param Context $context
     */
    public function markReminded(string $id, Context $context): void
    {
        $this->repoSubscriptions->update([
            [
                'id' => $id,
                'lastRemindedAt' => new DateTime(),
            ]
        ],
            $context
        );
    }

    /**
     * @param string $id
     * @param Context $context
     */
    public function cancelSubscription(string $id, Context $context): void
    {
        $this->repoSubscriptions->update([
            [
                'id' => $id,
                'canceledAt' => new DateTime(),
            ]
        ],
            $context
        );
    }

}
