<?php

namespace Mollie\Shopware\Entity\Order;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

class MollieShopwareOrder
{
    private OrderEntity $order;

    /**
     * @param OrderEntity $order
     */
    public function __construct(OrderEntity $order)
    {
        $this->order = $order;
    }

    /**
     * @return null|OrderTransactionEntity
     */
    public function getLatestTransaction(): ?OrderTransactionEntity
    {
        if (!$this->order->getTransactions() instanceof OrderTransactionCollection) {
            return null;
        }

        $latestTransaction = null;

        foreach ($this->order->getTransactions() as $transaction) {
            if ($latestTransaction === null || $transaction->getCreatedAt() > $latestTransaction->getCreatedAt()) {
                $latestTransaction = $transaction;
            }
        }

        return $latestTransaction;
    }
}
