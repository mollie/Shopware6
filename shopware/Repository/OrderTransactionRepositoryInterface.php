<?php
declare(strict_types=1);

namespace Mollie\Shopware\Repository;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

interface OrderTransactionRepositoryInterface
{
    public function findOpenTransactions(?Context $context = null): IdSearchResult;
}
