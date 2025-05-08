<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Transaction;

use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\TransactionService;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct as ShopwarePaymentTransactionStruct;
use Shopware\Core\Framework\Context;

/**
 * Since Shopware 6.7 there are two different Transaction Structs. PaymentTransactionStruct is in 6.7 and AsyncPaymentTransactionStruct in 6.6 and below.
 * With this class we create our own Transaction which handle the compatibility logic
 */
final class TransactionConverter implements TransactionConverterInterface
{
    private OrderService $orderService;
    private TransactionService $transactionService;

    public function __construct(OrderService $orderService, TransactionService $transactionService)
    {
        $this->orderService = $orderService;
        $this->transactionService = $transactionService;
    }

    /**
     * @param AsyncPaymentTransactionStruct|ShopwarePaymentTransactionStruct $transactionStruct
     */
    public function convert($transactionStruct, Context $context): PaymentTransactionStruct
    {
        $orderTransactionId = $this->getTransactionId($transactionStruct);

        $transaction = $this->transactionService->getTransactionById($orderTransactionId, $context->getVersionId(), $context);
        $order = $this->orderService->getOrder($transaction->getOrderId(), $context);

        return new PaymentTransactionStruct($orderTransactionId, (string) $transactionStruct->getReturnUrl(), $order, $transaction);
    }

    /**
     * @param AsyncPaymentTransactionStruct|ShopwarePaymentTransactionStruct $transactionStruct
     */
    private function getTransactionId($transactionStruct): string
    {
        $orderTransactionId = null;
        if ($transactionStruct instanceof ShopwarePaymentTransactionStruct) {
            $orderTransactionId = $transactionStruct->getOrderTransactionId();
        }
        if ($transactionStruct instanceof AsyncPaymentTransactionStruct) {
            $orderTransactionId = $transactionStruct->getOrderTransaction()->getId();
        }
        if ($orderTransactionId === null) {
            throw new \Exception(sprintf('Invalid transaction struct. OrderTransaction Id could not be found in class %s', get_class($transactionStruct)));
        }

        return $orderTransactionId;
    }
}
