<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

final class FakeCustomerRepository extends EntityRepository
{
    /** @var list<array<string, mixed>> */
    private array $upserts = [];

    public function __construct(private CustomerCollection $collection = new CustomerCollection())
    {
    }

    public function add(CustomerEntity $customer): void
    {
        $this->collection->add($customer);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $ids = $criteria->getIds();
        if ($ids === []) {
            return new EntitySearchResult(CustomerEntity::class, $this->collection->count(), $this->collection, null, $criteria, $context);
        }

        $filtered = new CustomerCollection();
        foreach ($this->collection as $customer) {
            if (in_array($customer->getId(), $ids, true)) {
                $filtered->add($customer);
            }
        }

        return new EntitySearchResult(CustomerEntity::class, $filtered->count(), $filtered, null, $criteria, $context);
    }

    /**
     * @param array<int, array<string, mixed>> $data
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        foreach ($data as $entry) {
            $this->upserts[] = $entry;
        }

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }

    /** @return array<string, mixed> */
    public function getLastUpsert(): array
    {
        if ($this->upserts === []) {
            throw new \RuntimeException('No upserts recorded.');
        }

        return $this->upserts[array_key_last($this->upserts)];
    }
}
