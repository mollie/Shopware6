<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\OrderDelivery;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

interface OrderDeliveryInterface
{
    public function upsert(array $data, Context $context): void;
    public function create(array $data, Context $context): void;
    public function search(Criteria $criteria, Context $context): EntitySearchResult;
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult;
    public function update(array $data, Context $context): EntityWrittenContainerEvent;
}
