<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

final class FakeOrderEntityRepository extends EntityRepository
{
    /** @var list<array<string,mixed>> */
    private array $upsertedPayloads = [];

    public function __construct()
    {
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
            throw new \RuntimeException('FakeOrderEntityRepository has no upsert payloads recorded.');
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

    /**
     * @param array<int,array<string,mixed>> $data
     */
    public function upsert(array $data, Context $context): EntityWrittenContainerEvent
    {
        foreach ($data as $entry) {
            $this->upsertedPayloads[] = $entry;
        }

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }
}
