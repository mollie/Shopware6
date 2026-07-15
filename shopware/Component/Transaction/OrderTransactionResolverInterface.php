<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

interface OrderTransactionResolverInterface
{
    /**
     * The transaction that represents the order's current payment - the same one Shopware shows in the
     * admin: the first transaction (createdAt ascending) whose state is neither cancelled nor failed,
     * falling back to the newest transaction when every transaction is cancelled/failed.
     */
    public function resolveEffective(OrderEntity $order): ?OrderTransactionEntity;

    /**
     * The latest authorized transaction that can still be captured on shipment - i.e. only while the
     * order has no paid transaction yet. Returns null when the order is already paid or has no authorized
     * transaction.
     */
    public function resolveCapturableAuthorized(OrderEntity $order): ?OrderTransactionEntity;

    /**
     * The transaction that already holds committed money at Mollie: the paid transaction if present,
     * otherwise the latest capturable authorized one. Used by the paid-guard to reuse the existing Mollie
     * payment instead of creating a second one. Returns null when the order has neither.
     */
    public function resolveSettled(OrderEntity $order): ?OrderTransactionEntity;

    /**
     * The transaction that holds the captured, refundable payment. After the first refund its state moves
     * paid -> partially_refunded -> refunded, so all three are accepted.
     */
    public function resolveRefundable(OrderEntity $order): ?OrderTransactionEntity;

    /**
     * Whether the order has any paid transaction.
     */
    public function hasPaidTransaction(OrderEntity $order): bool;
}
