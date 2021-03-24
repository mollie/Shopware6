<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Mollie\Api\Resources\Order as MollieOrder;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class ChangeTransactionStatus
{

    public function changeTransactionStatus(MollieOrder $mollieOrder, OrderTransactionEntity $transactionEntity): void
    {
        $targetStatus = $mollieOrder->status;

        $mollieOrder->isCanceled();

    }
}
