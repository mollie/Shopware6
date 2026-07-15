<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Transaction;

use Mollie\Shopware\Component\Transaction\OrderTransactionResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[CoversClass(OrderTransactionResolver::class)]
final class OrderTransactionResolverTest extends TestCase
{
    private OrderTransactionResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OrderTransactionResolver();
    }

    public function testResolveEffectiveReturnsNullWhenOrderHasNoTransactions(): void
    {
        self::assertNull($this->resolver->resolveEffective(new OrderEntity()));
    }

    /**
     * Mirrors the Shopware admin: the first transaction (createdAt ascending) that is neither cancelled
     * nor failed - even when a newer cancelled/failed transaction exists.
     */
    public function testResolveEffectiveReturnsFirstNonCancelledFailedByCreatedAt(): void
    {
        $oldestCancelled = $this->createTransaction('oldest', OrderTransactionStates::STATE_CANCELLED, 1000);
        $paid = $this->createTransaction('paid', OrderTransactionStates::STATE_PAID, 2000);
        $newestFailed = $this->createTransaction('newest', OrderTransactionStates::STATE_FAILED, 3000);

        // Insertion order deliberately differs from createdAt order.
        $order = $this->createOrder($newestFailed, $oldestCancelled, $paid);

        self::assertSame($paid, $this->resolver->resolveEffective($order));
    }

    public function testResolveEffectiveReturnsNewestWhenAllCancelledOrFailed(): void
    {
        $oldestCancelled = $this->createTransaction('oldest', OrderTransactionStates::STATE_CANCELLED, 1000);
        $newestFailed = $this->createTransaction('newest', OrderTransactionStates::STATE_FAILED, 3000);

        $order = $this->createOrder($oldestCancelled, $newestFailed);

        self::assertSame($newestFailed, $this->resolver->resolveEffective($order));
    }

    public function testResolveCapturableAuthorizedReturnsLatestAuthorized(): void
    {
        $olderAuthorized = $this->createTransaction('older', OrderTransactionStates::STATE_AUTHORIZED, 1000);
        $newerAuthorized = $this->createTransaction('newer', OrderTransactionStates::STATE_AUTHORIZED, 2000);

        $order = $this->createOrder($olderAuthorized, $newerAuthorized);

        self::assertSame($newerAuthorized, $this->resolver->resolveCapturableAuthorized($order));
    }

    public function testResolveCapturableAuthorizedReturnsNullWhenOrderIsAlreadyPaid(): void
    {
        $authorized = $this->createTransaction('authorized', OrderTransactionStates::STATE_AUTHORIZED, 1000);
        $paid = $this->createTransaction('paid', OrderTransactionStates::STATE_PAID, 2000);

        $order = $this->createOrder($authorized, $paid);

        self::assertNull($this->resolver->resolveCapturableAuthorized($order));
    }

    public function testResolveSettledPrefersPaidOverAuthorized(): void
    {
        $authorized = $this->createTransaction('authorized', OrderTransactionStates::STATE_AUTHORIZED, 1000);
        $paid = $this->createTransaction('paid', OrderTransactionStates::STATE_PAID, 2000);

        $order = $this->createOrder($authorized, $paid);

        self::assertSame($paid, $this->resolver->resolveSettled($order));
    }

    public function testResolveSettledFallsBackToAuthorizedWhenNotPaid(): void
    {
        $authorized = $this->createTransaction('authorized', OrderTransactionStates::STATE_AUTHORIZED, 1000);

        $order = $this->createOrder($authorized);

        self::assertSame($authorized, $this->resolver->resolveSettled($order));
    }

    public function testResolveSettledReturnsNullWhenNeitherPaidNorAuthorized(): void
    {
        $order = $this->createOrder($this->createTransaction('open', OrderTransactionStates::STATE_OPEN, 1000));

        self::assertNull($this->resolver->resolveSettled($order));
    }

    public function testResolveRefundableAcceptsPaidPartiallyRefundedAndRefunded(): void
    {
        foreach ([
            OrderTransactionStates::STATE_PAID,
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
            OrderTransactionStates::STATE_REFUNDED,
        ] as $state) {
            $transaction = $this->createTransaction('tx-' . $state, $state, 1000);
            $order = $this->createOrder($transaction);

            self::assertSame($transaction, $this->resolver->resolveRefundable($order), $state);
        }
    }

    public function testResolveRefundableReturnsNullForNonRefundableStates(): void
    {
        $order = $this->createOrder($this->createTransaction('authorized', OrderTransactionStates::STATE_AUTHORIZED, 1000));

        self::assertNull($this->resolver->resolveRefundable($order));
    }

    public function testHasPaidTransaction(): void
    {
        $paidOrder = $this->createOrder($this->createTransaction('paid', OrderTransactionStates::STATE_PAID, 1000));
        $openOrder = $this->createOrder($this->createTransaction('open', OrderTransactionStates::STATE_OPEN, 1000));

        self::assertTrue($this->resolver->hasPaidTransaction($paidOrder));
        self::assertFalse($this->resolver->hasPaidTransaction($openOrder));
    }

    private function createOrder(OrderTransactionEntity ...$transactions): OrderEntity
    {
        $order = new OrderEntity();
        $order->setTransactions(new OrderTransactionCollection($transactions));

        return $order;
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
