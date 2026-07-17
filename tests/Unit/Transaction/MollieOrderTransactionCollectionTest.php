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
