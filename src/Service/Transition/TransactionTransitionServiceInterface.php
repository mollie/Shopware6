<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Transition;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;

interface TransactionTransitionServiceInterface
{
    public function processTransaction(OrderTransactionEntity $transaction, Context $context): void;

    public function reOpenTransaction(OrderTransactionEntity $transaction, Context $context): void;
}
