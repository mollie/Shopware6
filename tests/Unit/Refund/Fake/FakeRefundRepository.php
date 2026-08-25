<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Refund\Fake;

use Mollie\Shopware\Component\Refund\DAL\Refund\RefundCollection;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundDefinition;
use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

final class FakeRefundRepository extends EntityRepository
{
    private RefundCollection $collection;

    public function __construct()
    {
        $this->collection = new RefundCollection();
    }

    public function add(RefundEntity $refund): void
    {
        $this->collection->add($refund);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $ids = $criteria->getIds();

        $filtered = new RefundCollection();
        foreach ($this->collection as $refund) {
            if ($ids !== [] && ! in_array($refund->getId(), $ids, true)) {
                continue;
            }
            $filtered->add($refund);
        }

        return new EntitySearchResult(RefundDefinition::ENTITY_NAME, $filtered->count(), $filtered, null, $criteria, $context);
    }
}
