<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Repository;

use Mollie\Shopware\Mollie;
use Mollie\Shopware\Repository\OrderTransactionRepository;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeOrderTransactionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

#[CoversClass(OrderTransactionRepository::class)]
final class OrderTransactionRepositoryTest extends TestCase
{
    private FakeOrderTransactionRepository $dalRepository;

    private OrderTransactionRepository $repository;

    protected function setUp(): void
    {
        $this->dalRepository = new FakeOrderTransactionRepository();
        $this->repository = new OrderTransactionRepository($this->dalRepository, new FakeLogger());
    }

    public function testOpenTransactionsAreAnsweredWithTheirIds(): void
    {
        $this->dalRepository->setMatchingIds('transaction-1', 'transaction-2');

        $result = $this->repository->findOpenTransactions(Context::createDefaultContext());

        self::assertSame(['transaction-1', 'transaction-2'], $result->getIds());
    }

    public function testTheLookupWorksWithoutAGivenContext(): void
    {
        // The scheduled task that polls unfinished payments has no context of its own.
        $this->dalRepository->setMatchingIds('transaction-1');

        $result = $this->repository->findOpenTransactions();

        self::assertSame(['transaction-1'], $result->getIds());
    }

    public function testOnlyUnfinishedPaymentsAreLookedUp(): void
    {
        $this->repository->findOpenTransactions(Context::createDefaultContext());

        $stateNames = [];
        foreach ($this->stateFilter()->getQueries() as $query) {
            self::assertInstanceOf(EqualsFilter::class, $query);
            $stateNames[] = $query->getValue();
        }

        self::assertContains(OrderTransactionStates::STATE_IN_PROGRESS, $stateNames);
    }

    public function testOnlyMollieOrdersAreLookedUp(): void
    {
        // The Mollie data sits on the order for legacy orders and on the transaction for current ones,
        // so either side counts.
        $this->repository->findOpenTransactions(Context::createDefaultContext());

        $fields = [];
        foreach ($this->mollieFilter()->getQueries() as $query) {
            self::assertInstanceOf(ContainsFilter::class, $query);
            $fields[$query->getField()] = $query->getValue();
        }

        self::assertSame([
            'order.customFields' => Mollie::EXTENSION,
            'customFields' => Mollie::EXTENSION,
        ], $fields);
    }

    public function testOrdersYoungerThanFiveMinutesAndOlderThan101DaysAreSkipped(): void
    {
        // A payment that was just started is still being paid, and one older than the longest Mollie
        // expiry can no longer change.
        $this->repository->findOpenTransactions(Context::createDefaultContext());

        $range = $this->rangeFilter();
        $from = new \DateTimeImmutable((string) $range->getParameter(RangeFilter::GTE));
        $to = new \DateTimeImmutable((string) $range->getParameter(RangeFilter::LTE));
        $span = $from->diff($to);

        self::assertSame('order.orderDateTime', $range->getField());
        // 101 days, minus the five-minute grace period at the upper end
        self::assertSame(100, $span->days);
        self::assertSame(23, $span->h);
        self::assertSame(55, $span->i);
    }

    public function testTheNewestOrdersAreHandledFirstAndInBatchesOfTen(): void
    {
        $this->repository->findOpenTransactions(Context::createDefaultContext());

        $criteria = $this->criteria();
        self::assertSame(10, $criteria->getLimit());
        self::assertSame('order.orderDateTime', $criteria->getSorting()[0]->getField());
        self::assertSame(FieldSorting::DESCENDING, $criteria->getSorting()[0]->getDirection());
    }

    public function testTheAssociationsTheCallerReadsAreLoaded(): void
    {
        $this->repository->findOpenTransactions(Context::createDefaultContext());

        $associations = array_keys($this->criteria()->getAssociations());

        self::assertContains('stateMachineState', $associations);
        self::assertContains('paymentMethod', $associations);
        self::assertContains('order', $associations);
    }

    private function criteria(): Criteria
    {
        $criteria = $this->dalRepository->getSearchIdsCriteria();
        self::assertCount(1, $criteria);

        return $criteria[0];
    }

    private function stateFilter(): OrFilter
    {
        return $this->orFilterWith(EqualsFilter::class);
    }

    private function mollieFilter(): OrFilter
    {
        return $this->orFilterWith(ContainsFilter::class);
    }

    private function rangeFilter(): RangeFilter
    {
        foreach ($this->criteria()->getFilters() as $filter) {
            if ($filter instanceof RangeFilter) {
                return $filter;
            }
        }

        self::fail('The lookup does not restrict the order date.');
    }

    /**
     * @param class-string $queryClass
     */
    private function orFilterWith(string $queryClass): OrFilter
    {
        foreach ($this->criteria()->getFilters() as $filter) {
            if (! $filter instanceof OrFilter) {
                continue;
            }
            if ($filter->getQueries()[0] instanceof $queryClass) {
                return $filter;
            }
        }

        self::fail(sprintf('The lookup has no OR filter built from %s.', $queryClass));
    }
}
