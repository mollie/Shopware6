<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\CustomerAddress;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

interface CustomerAddressRepositoryInterface
{
    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent;

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent;

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult;

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent;

    /**
     * @param array<mixed> $ids
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent;
}
