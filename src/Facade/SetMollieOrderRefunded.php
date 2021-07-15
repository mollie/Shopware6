<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Exception\CouldNotSetRefundAtMollieException;
use Kiener\MolliePayments\Service\RefundService;
use Kiener\MolliePayments\Service\TransactionService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class SetMollieOrderRefunded
{
    /**
     * @var RefundService
     */
    private $refundService;

    /**
     * @var TransactionService
     */
    private $transactionService;

    public function __construct(TransactionService $transactionService, RefundService $refundService)
    {
        $this->transactionService = $transactionService;
        $this->refundService = $refundService;
    }

    /**
     * @param string $orderTransactionId
     * @param Context $context
     * @throws CouldNotSetRefundAtMollieException
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function setRefunded(string $orderTransactionId, Context $context): void
    {
        $transaction = $this->transactionService->getTransactionById($orderTransactionId, null, $context);

        if (!$transaction instanceof OrderTransactionEntity) {
            throw new CouldNotSetRefundAtMollieException(
                sprintf('Could not find transaction %s ', $orderTransactionId)
            );
        }

        $order = $transaction->getOrder();

        if (!$order instanceof OrderEntity) {
            throw new CouldNotSetRefundAtMollieException(
                sprintf('Could not find order for transaction %s ', $transaction->getId())
            );
        }

        $refunded = $this->refundService->getRefundedAmount($order, $context);
        $toRefund = $order->getAmountTotal() - $refunded;

        if ($toRefund <= 0.0) {
            return;
        }

        $this->refundService->refund(
            $order,
            $toRefund,
            sprintf(
                "Refunded entire order through Shopware Administration. Order number %s",
                $order->getOrderNumber()
            ),
            $context
        );
    }
}
