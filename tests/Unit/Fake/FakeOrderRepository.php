<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\NestedEventCollection;

final class FakeOrderRepository extends EntityRepository
{
    /** @var list<array<string,mixed>> */
    private array $upsertedPayloads = [];

    /** @var list<array<string,mixed>> */
    private array $updatedPayloads = [];

    /** @var list<string> */
    private array $updateScopes = [];

    private OrderCollection $orders;

    public function __construct()
    {
        $this->orders = new OrderCollection();
    }

    public function add(OrderEntity $order): void
    {
        $this->orders->add($order);
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        $ids = $criteria->getIds();

        $found = new OrderCollection();
        foreach ($this->orders as $order) {
            if ($ids !== [] && ! in_array($order->getId(), $ids, true)) {
                continue;
            }
            if (! $this->matchesFilters($order, $criteria)) {
                continue;
            }
            $found->add($order);
        }

        return new EntitySearchResult(OrderEntity::class, $found->count(), $found, null, $criteria, $context);
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
            throw new \RuntimeException('FakeOrderRepository has no upsert payloads recorded.');
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

    public function getUpdateCount(): int
    {
        return count($this->updatedPayloads);
    }

    /**
     * @return array<string,mixed>
     */
    public function getLastUpdate(): array
    {
        if ($this->updatedPayloads === []) {
            throw new \RuntimeException('FakeOrderRepository has no update payloads recorded.');
        }

        return $this->updatedPayloads[array_key_last($this->updatedPayloads)];
    }

    /**
     * Write-protected fields such as billingAddressId only go through in the system scope, so a
     * caller that forgot to switch has to be visible to a test.
     */
    public function getLastUpdateScope(): string
    {
        if ($this->updateScopes === []) {
            throw new \RuntimeException('FakeOrderRepository has no update payloads recorded.');
        }

        return $this->updateScopes[array_key_last($this->updateScopes)];
    }

    /**
     * @param array<int,array<string,mixed>> $data
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        foreach ($data as $entry) {
            $this->updatedPayloads[] = $entry;
        }
        $this->updateScopes[] = $context->getScope();

        return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
    }

    private function matchesFilters(OrderEntity $order, Criteria $criteria): bool
    {
        foreach ($criteria->getFilters() as $filter) {
            if (! $filter instanceof EqualsFilter) {
                continue;
            }

            if ($filter->getField() === 'salesChannelId' && $order->getSalesChannelId() !== $filter->getValue()) {
                return false;
            }

            if ($filter->getField() === 'orderCustomer.customerId' && $order->getOrderCustomer()?->getCustomerId() !== $filter->getValue()) {
                return false;
            }
        }

        return true;
    }
}
