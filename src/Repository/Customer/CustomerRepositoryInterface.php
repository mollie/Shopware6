<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Customer;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

interface CustomerRepositoryInterface
{
    public function upsert(array $data, Context $context): void;
    public function create(array $data, Context $context): void;

    public function search(Criteria $criteria, Context $context): EntitySearchResult;
}
