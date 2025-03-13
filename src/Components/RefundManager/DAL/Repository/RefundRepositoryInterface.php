<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\Repository;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

interface RefundRepositoryInterface
{
    /**
     * @param array<mixed> $data
     */
    public function create(array $data, Context $context): EntityWrittenContainerEvent;

    public function search(Criteria $criteria, Context $context): EntitySearchResult;

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult;

    /**
     * @param array<mixed> $ids
     */
    public function delete(array $ids, Context $context): EntityWrittenContainerEvent;
}
