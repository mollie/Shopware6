<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

final class FakeTransactionRepository extends EntityRepository
{
    /** @var list<string> */
    private array $matchingIds = [];

    public function __construct()
    {
    }

    public function setMatchingIds(string ...$ids): void
    {
        $this->matchingIds = array_values($ids);
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $data = [];
        foreach ($this->matchingIds as $id) {
            $data[] = ['data' => ['id' => $id], 'primaryKey' => $id];
        }

        return new IdSearchResult(count($data), $data, $criteria, $context);
    }
}
