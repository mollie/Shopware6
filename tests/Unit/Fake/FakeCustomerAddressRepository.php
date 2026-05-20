<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;

final class FakeCustomerAddressRepository extends EntityRepository
{
    /** @var array<string,bool> */
    private array $existingIds = [];

    /** @var list<array<string,mixed>> */
    private array $upsertedPayloads = [];

    public function __construct()
    {
    }

    public function registerExistingId(string $id): void
    {
        $this->existingIds[$id] = true;
    }

    public function getUpsertCount(): int
    {
        return count($this->upsertedPayloads);
    }

    /**
     * @return array<string,mixed>
     */
    public function getLastUpsert(): array
    {
        if ($this->upsertedPayloads === []) {
            throw new \RuntimeException('FakeCustomerAddressRepository has no upsert payloads recorded.');
        }

        return $this->upsertedPayloads[array_key_last($this->upsertedPayloads)];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getUpserts(): array
    {
        return $this->upsertedPayloads;
    }

    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        $found = [];
        foreach ($criteria->getIds() as $id) {
            if (! is_string($id)) {
                continue;
            }
            if (isset($this->existingIds[$id])) {
                $found[] = ['data' => ['id' => $id], 'primaryKey' => $id];
            }
        }

        return new IdSearchResult(count($found), $found, $criteria, $context);
    }

    /**
     * @param array<int,array<string,mixed>> $data
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        foreach ($data as $entry) {
            $this->upsertedPayloads[] = $entry;
            if (isset($entry['id']) && is_string($entry['id'])) {
                $this->existingIds[$entry['id']] = true;
            }
        }

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }
}
