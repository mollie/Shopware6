<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Media;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

interface MediaRepositoryInterface
{
    public function upsert(array $data, Context $context): void;
    public function create(array $data, Context $context): void;
    public function search(Criteria $criteria, Context $context): EntitySearchResult;
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult;
}
