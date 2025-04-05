<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Struct\Struct;

final class PaymentTransactionStruct extends Struct
{
    private string $orderTransactionId;
    private string $returnUrl;
    private OrderEntity $order;
    private OrderTransactionEntity $orderTransaction;

    public function __construct(string $orderTransactionId, string $returnUrl, OrderEntity $order, OrderTransactionEntity $orderTransaction)
    {
        $this->orderTransactionId = $orderTransactionId;
        $this->returnUrl = $returnUrl;
        $this->order = $order;
        $this->orderTransaction = $orderTransaction;
    }

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }
}
