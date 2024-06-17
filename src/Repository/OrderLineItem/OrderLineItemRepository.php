<?php

namespace Kiener\MolliePayments\Repository\OrderLineItem;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class OrderLineItemRepository implements OrderLineItemRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $repoOrderLineItems;

    /**
     * @param EntityRepository $repoOrderLineItems
     */
    public function __construct($repoOrderLineItems)
    {
        $this->repoOrderLineItems = $repoOrderLineItems;
    }


    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->repoOrderLineItems->update($data, $context);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->repoOrderLineItems->search($criteria, $context);
    }
}
