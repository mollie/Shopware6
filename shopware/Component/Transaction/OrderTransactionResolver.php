<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * Selects the relevant order transaction for the different Mollie flows. Additional (e.g. cancelled or
 * failed) transactions can be created on an order after the fact, so the newest transaction by createdAt
 * is not reliable on its own - selection is always done over the whole collection by state.
 */
final class OrderTransactionResolver implements OrderTransactionResolverInterface
{
    public function resolveEffective(OrderEntity $order): ?OrderTransactionEntity
    {
        $newest = null;
        foreach ($this->sortedByCreatedAt($order) as $transaction) {
            $newest = $transaction;

            $state = $transaction->getStateMachineState();
            if ($state === null) {
                continue;
            }

            if (! in_array($state->getTechnicalName(), [OrderTransactionStates::STATE_CANCELLED, OrderTransactionStates::STATE_FAILED], true)) {
                return $transaction;
            }
        }

        return $newest;
    }

    public function resolveCapturableAuthorized(OrderEntity $order): ?OrderTransactionEntity
    {
        if ($this->hasPaidTransaction($order)) {
            return null;
        }

        return $this->latestInStates($order, [OrderTransactionStates::STATE_AUTHORIZED]);
    }

    public function resolveSettled(OrderEntity $order): ?OrderTransactionEntity
    {
        $paid = $this->latestInStates($order, [OrderTransactionStates::STATE_PAID]);
        if ($paid !== null) {
            return $paid;
        }

        return $this->resolveCapturableAuthorized($order);
    }

    public function resolveRefundable(OrderEntity $order): ?OrderTransactionEntity
    {
        return $this->latestInStates($order, [
            OrderTransactionStates::STATE_PAID,
            OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
            OrderTransactionStates::STATE_REFUNDED,
        ]);
    }

    public function hasPaidTransaction(OrderEntity $order): bool
    {
        return $this->latestInStates($order, [OrderTransactionStates::STATE_PAID]) !== null;
    }

    /**
     * @param list<string> $technicalNames
     */
    private function latestInStates(OrderEntity $order, array $technicalNames): ?OrderTransactionEntity
    {
        $match = null;
        foreach ($this->sortedByCreatedAt($order) as $transaction) {
            $state = $transaction->getStateMachineState();
            if ($state === null) {
                continue;
            }

            if (in_array($state->getTechnicalName(), $technicalNames, true)) {
                $match = $transaction;
            }
        }

        return $match;
    }

    /**
     * @return list<OrderTransactionEntity>
     */
    private function sortedByCreatedAt(OrderEntity $order): array
    {
        $transactions = $order->getTransactions();
        if ($transactions === null) {
            return [];
        }

        $elements = array_values($transactions->getElements());
        usort($elements, function (OrderTransactionEntity $a, OrderTransactionEntity $b): int {
            return ($a->getCreatedAt()?->getTimestamp() ?? 0) <=> ($b->getCreatedAt()?->getTimestamp() ?? 0);
        });

        return $elements;
    }
}
