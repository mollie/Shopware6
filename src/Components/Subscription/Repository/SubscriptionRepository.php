<?php

namespace Kiener\MolliePayments\Components\Subscription\Repository;


use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
     * @param string $orderId
     * @param Context $context
     * @return EntitySearchResult
     */
    public function getPendingSubscriptions(string $orderId, Context $context): EntitySearchResult
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
                # ----------------------------------------------------------
                'description' => $subscription->getDescription(),
                'amount' => $subscription->getAmount(),
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
     * @param Context $context
     */
    public function confirmSubscription(string $id, string $mollieSubscriptionId, string $mollieCustomerId, Context $context): void
    {
        $this->repoSubscriptions->update([
            [
                'id' => $id,
                'mollieId' => $mollieSubscriptionId,
                'mollieCustomerId' => $mollieCustomerId,
            ]
        ],
            $context
        );
    }

}
