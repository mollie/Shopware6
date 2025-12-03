<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Mollie\Shopware\Component\Mollie\Payment;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

interface TransactionServiceInterface
{
    public function findById(string $transactionId, Context $context): TransactionDataStruct;

    public function savePaymentExtension(string $transactionId, OrderEntity $order, Payment $payment, Context $context): EntityWrittenContainerEvent;
}
