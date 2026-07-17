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
        if ($this->transactions === null) {
            return null;
        }

        $elements = array_values($this->transactions->getElements());
        usort($elements, function (OrderTransactionEntity $a, OrderTransactionEntity $b): int {
            return ($a->getCreatedAt()?->getTimestamp() ?? 0) <=> ($b->getCreatedAt()?->getTimestamp() ?? 0);
        });

        $newest = null;
        foreach ($elements as $transaction) {
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
}
