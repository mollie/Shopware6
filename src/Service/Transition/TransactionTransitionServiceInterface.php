<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Transition;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;

interface TransactionTransitionServiceInterface
{
    public function processTransaction(OrderTransactionEntity $transaction, Context $context): void;

    public function reOpenTransaction(OrderTransactionEntity $transaction, Context $context): void;

    public function payTransaction(OrderTransactionEntity $transaction, Context $context): void;

    public function cancelTransaction(OrderTransactionEntity $transaction, Context $context): void;

    public function failTransaction(OrderTransactionEntity $transaction, Context $context): void;

    public function authorizeTransaction(OrderTransactionEntity $transaction, Context $context): void;

    public function refundTransaction(OrderTransactionEntity $transaction, Context $context): void;

    public function partialRefundTransaction(OrderTransactionEntity $transaction, Context $context): void;

    public function chargebackTransaction(OrderTransactionEntity $transaction, Context $context): void;
}
