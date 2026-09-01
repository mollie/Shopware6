<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

/**
 * Dispatched by the admin actions (ship, cancel item, refund) right before they read the Mollie payment
 * extension of a transaction. Legacy orders (created by older plugin versions) only carry the Mollie data
 * on the order and the Finalize repair that copies it onto the transaction is not always run beforehand.
 * A subscriber can use the carried transaction/order to reconstruct the missing data so the following flow
 * finds the extension.
 */
final class RepairLegacyTransactionEvent
{
    public function __construct(
        private readonly OrderTransactionEntity $transaction,
        private readonly OrderEntity $order,
        private readonly Context $context,
    ) {
    }

    public function getTransaction(): OrderTransactionEntity
    {
        return $this->transaction;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
