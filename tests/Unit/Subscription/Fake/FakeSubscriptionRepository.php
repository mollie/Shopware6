<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

final class FakeSubscriptionRepository extends EntityRepository
{
    /** @var list<array<string,mixed>> */
    public array $upsertedPayloads = [];

    public function __construct(private SubscriptionCollection $collection = new SubscriptionCollection())
    {
    }

    public function add(SubscriptionEntity $subscription): void
    {
        $this->collection->add($subscription);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $ids = $criteria->getIds();
        $filtered = new SubscriptionCollection();

        if ($ids === []) {
            $filtered = $this->collection;
        } else {
            foreach ($this->collection as $subscription) {
                if (in_array($subscription->getId(), $ids, true)) {
                    $filtered->add($subscription);
                }
            }
        }

        return new EntitySearchResult(SubscriptionEntity::class, $filtered->count(), $filtered, null, $criteria, $context);
    }

    /**
     * @param array<int,array<string,mixed>> $data
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        foreach ($data as $entry) {
            $this->upsertedPayloads[] = $entry;
        }

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }
}
