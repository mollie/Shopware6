<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;

/**
 * Wraps the order's Shopware transaction collection to expose the single transaction that represents
 * the order's current payment - the same one Shopware shows the payment status for in the admin.
 */
final class MollieOrderTransactionCollection
{
    public function __construct(private readonly ?OrderTransactionCollection $transactions)
    {
    }

    /**
     * Mirrors Shopware's admin payment-status selection: the oldest transaction (by createdAt) whose
     * state is neither cancelled nor failed, falling back to the newest when every transaction is
     * cancelled/failed. Additional cancelled/failed transactions created on retries are therefore
     * ignored, and the transaction the merchant sees as current is the one we ship/cancel/refund.
     */
    public function getCurrentOrderTransaction(): ?OrderTransactionEntity
    {
        // Oldest first, fall back to the newest: additional cancelled/failed retries are skipped, so
        // the transaction the merchant sees as current is the one we ship/cancel/refund.
        return $this->findTransaction(
            [OrderTransactionStates::STATE_CANCELLED, OrderTransactionStates::STATE_FAILED],
            false,
            true,
            true
        );
    }

    /**
     * The newest transaction (by createdAt) that still awaits payment (state open or reminded) -
     * i.e. the one a payment link should be paid for. Returns null when there is none.
     */
    public function getLatestPayableTransaction(): ?OrderTransactionEntity
    {
        return $this->findTransaction(
            [OrderTransactionStates::STATE_OPEN, OrderTransactionStates::STATE_REMINDED],
            true,
            false,
            false
        );
    }

    /**
     * Returns the first transaction (by createdAt, newest first when $newestFirst) whose state is
     * in $states, or - when $exclude - the first whose state is not in $states. Falls back to the
     * newest transaction when nothing matches and $fallbackToNewest is set, otherwise to null.
     *
     * @param string[] $states
     */
    private function findTransaction(array $states, bool $newestFirst, bool $exclude, bool $fallbackToNewest): ?OrderTransactionEntity
    {
        if ($this->transactions === null) {
            return null;
        }

        $elements = array_values($this->transactions->getElements());
        usort($elements, function (OrderTransactionEntity $a, OrderTransactionEntity $b) use ($newestFirst): int {
            $comparison = ($a->getCreatedAt()?->getTimestamp() ?? 0) <=> ($b->getCreatedAt()?->getTimestamp() ?? 0);

            return $newestFirst ? -$comparison : $comparison;
        });

        foreach ($elements as $transaction) {
            $state = $transaction->getStateMachineState();
            if ($state === null) {
                continue;
            }

            if (in_array($state->getTechnicalName(), $states, true) !== $exclude) {
                return $transaction;
            }
        }

        if (! $fallbackToNewest || count($elements) === 0) {
            return null;
        }

        // Elements are sorted, so the newest is first when $newestFirst, otherwise last.
        return $newestFirst ? $elements[0] : $elements[array_key_last($elements)];
    }
}
