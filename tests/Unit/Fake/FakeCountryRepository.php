<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;

final class FakeCountryRepository extends EntityRepository
{
    /** @param list<CountryEntity> $countries */
    public function __construct(private readonly array $countries = [])
    {
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $collection = new CountryCollection($this->countries);

        return new EntitySearchResult(CountryEntity::class, $collection->count(), $collection, null, $criteria, $context);
    }
}
