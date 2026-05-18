<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

final class FakeCustomerAddressSearchRepository extends EntityRepository
{
    /** @var list<CustomerAddressEntity> */
    private array $entities;

    /** @var list<array<string, mixed>> */
    private array $upserts = [];

    /** @param list<CustomerAddressEntity> $entities */
    public function __construct(array $entities = [])
    {
        $this->entities = $entities;
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $collection = new CustomerAddressCollection($this->entities);

        return new EntitySearchResult(CustomerAddressEntity::class, $collection->count(), $collection, null, $criteria, $context);
    }

    /** @param list<array<string, mixed>> $data */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        foreach ($data as $entry) {
            $this->upserts[] = $entry;
        }

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }

    /** @return list<array<string, mixed>> */
    public function getUpserts(): array
    {
        return $this->upserts;
    }
}
