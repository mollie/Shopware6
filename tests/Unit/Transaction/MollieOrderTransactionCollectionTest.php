<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Transaction;

use Mollie\Shopware\Component\Transaction\MollieOrderTransactionCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[CoversClass(MollieOrderTransactionCollection::class)]
final class MollieOrderTransactionCollectionTest extends TestCase
{
    public function testReturnsNullWhenThereAreNoTransactions(): void
    {
        $transactions = new MollieOrderTransactionCollection(null);

        self::assertNull($transactions->getCurrentOrderTransaction());
    }

    /**
     * Mirrors the Shopware admin: the first transaction (createdAt ascending) that is neither cancelled
     * nor failed, even when a newer cancelled/failed transaction exists.
     */
    public function testReturnsFirstNonCancelledFailedByCreatedAt(): void
    {
        $oldestCancelled = $this->createTransaction('oldest', OrderTransactionStates::STATE_CANCELLED, 1000);
        $paid = $this->createTransaction('paid', OrderTransactionStates::STATE_PAID, 2000);
        $newestFailed = $this->createTransaction('newest', OrderTransactionStates::STATE_FAILED, 3000);

        // Insertion order deliberately differs from createdAt order.
        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$newestFailed, $oldestCancelled, $paid]));

        self::assertSame($paid, $transactions->getCurrentOrderTransaction());
    }

    public function testReturnsNewestWhenAllCancelledOrFailed(): void
    {
        $oldestCancelled = $this->createTransaction('oldest', OrderTransactionStates::STATE_CANCELLED, 1000);
        $newestFailed = $this->createTransaction('newest', OrderTransactionStates::STATE_FAILED, 3000);

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$oldestCancelled, $newestFailed]));

        self::assertSame($newestFailed, $transactions->getCurrentOrderTransaction());
    }

    public function testReturnsTheOnlyTransaction(): void
    {
        $authorized = $this->createTransaction('authorized', OrderTransactionStates::STATE_AUTHORIZED, 1000);

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$authorized]));

        self::assertSame($authorized, $transactions->getCurrentOrderTransaction());
    }

    public function testARetryMakesTheEarlierTransactionOutdated(): void
    {
        $first = $this->createTransaction('first', OrderTransactionStates::STATE_FAILED, 1000);
        $retry = $this->createTransaction('retry', OrderTransactionStates::STATE_OPEN, 2000);

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$first, $retry]));

        self::assertTrue($transactions->hasNewerTransactionThan($first));
    }

    public function testTheNewestTransactionIsNotOutdated(): void
    {
        $first = $this->createTransaction('first', OrderTransactionStates::STATE_FAILED, 1000);
        $retry = $this->createTransaction('retry', OrderTransactionStates::STATE_OPEN, 2000);

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$first, $retry]));

        self::assertFalse($transactions->hasNewerTransactionThan($retry));
    }

    public function testTheOnlyTransactionIsNotOutdatedByItself(): void
    {
        $only = $this->createTransaction('only', OrderTransactionStates::STATE_OPEN, 1000);

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$only]));

        self::assertFalse($transactions->hasNewerTransactionThan($only));
    }

    /**
     * Nothing may be skipped on incomplete data, so an undecidable case counts as not outdated.
     */
    public function testATransactionWithoutACreationDateIsNotOutdated(): void
    {
        $undated = new OrderTransactionEntity();
        $undated->setId('undated');
        $newer = $this->createTransaction('newer', OrderTransactionStates::STATE_OPEN, 2000);

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$undated, $newer]));

        self::assertFalse($transactions->hasNewerTransactionThan($undated));
    }

    public function testACandidateWithoutACreationDateDoesNotOutdateAnything(): void
    {
        $dated = $this->createTransaction('dated', OrderTransactionStates::STATE_OPEN, 1000);
        $undated = new OrderTransactionEntity();
        $undated->setId('undated');

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$dated, $undated]));

        self::assertFalse($transactions->hasNewerTransactionThan($dated));
    }

    public function testNothingIsOutdatedWhenTheOrderHasNoTransactions(): void
    {
        $transaction = $this->createTransaction('detached', OrderTransactionStates::STATE_OPEN, 1000);

        $transactions = new MollieOrderTransactionCollection(null);

        self::assertFalse($transactions->hasNewerTransactionThan($transaction));
    }

    public function testThePayableTransactionIsTheNewestOpenOne(): void
    {
        $olderOpen = $this->createTransaction('older-open', OrderTransactionStates::STATE_OPEN, 1000);
        $newerOpen = $this->createTransaction('newer-open', OrderTransactionStates::STATE_OPEN, 3000);

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$olderOpen, $newerOpen]));

        self::assertSame($newerOpen, $transactions->getLatestPayableTransaction());
    }

    public function testARemindedTransactionStillAwaitsPayment(): void
    {
        $reminded = $this->createTransaction('reminded', OrderTransactionStates::STATE_REMINDED, 2000);
        $failed = $this->createTransaction('failed', OrderTransactionStates::STATE_FAILED, 3000);

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$reminded, $failed]));

        self::assertSame($reminded, $transactions->getLatestPayableTransaction());
    }

    /**
     * Unlike the current transaction, this must not fall back to the newest one - a paid order has
     * nothing left to pay a payment link for.
     */
    public function testThereIsNothingPayableWhenEveryTransactionIsSettled(): void
    {
        $paid = $this->createTransaction('paid', OrderTransactionStates::STATE_PAID, 1000);
        $cancelled = $this->createTransaction('cancelled', OrderTransactionStates::STATE_CANCELLED, 2000);

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$paid, $cancelled]));

        self::assertNull($transactions->getLatestPayableTransaction());
    }

    public function testThereIsNothingPayableWithoutAnyTransaction(): void
    {
        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection());

        self::assertNull($transactions->getLatestPayableTransaction());
    }

    /**
     * A transaction whose state association was not loaded cannot be judged, so it is passed over
     * rather than treated as payable.
     */
    public function testATransactionWithoutALoadedStateIsPassedOver(): void
    {
        $stateless = new OrderTransactionEntity();
        $stateless->setId('stateless');
        $stateless->setCreatedAt((new \DateTimeImmutable())->setTimestamp(3000));
        $open = $this->createTransaction('open', OrderTransactionStates::STATE_OPEN, 1000);

        $transactions = new MollieOrderTransactionCollection(new OrderTransactionCollection([$stateless, $open]));

        self::assertSame($open, $transactions->getLatestPayableTransaction());
    }

    private function createTransaction(string $id, string $state, int $createdAtTimestamp): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId($id);
        $transaction->setCreatedAt((new \DateTimeImmutable())->setTimestamp($createdAtTimestamp));

        $stateEntity = new StateMachineStateEntity();
        $stateEntity->setId($state . '-state-id');
        $stateEntity->setTechnicalName($state);
        $transaction->setStateMachineState($stateEntity);

        return $transaction;
    }
}
