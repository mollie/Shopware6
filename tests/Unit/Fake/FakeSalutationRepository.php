<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;

final class FakeSalutationRepository extends EntityRepository
{
    /** @param list<SalutationEntity> $salutations */
    public function __construct(private readonly array $salutations = [])
    {
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $collection = new SalutationCollection($this->salutations);

        return new EntitySearchResult(SalutationEntity::class, $collection->count(), $collection, null, $criteria, $context);
    }
}
