<?php

namespace Kiener\MolliePayments\Facade;

use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\RefundService;
use Mollie\Api\Resources\Refund;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class MollieRefundFacade
{
    /** @var OrderService */
    private $orderService;

    /** @var RefundService */
    private $refundService;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        OrderService    $orderService,
        RefundService   $refundService,
        LoggerInterface $logger
    )
    {
        $this->orderService = $orderService;
        $this->refundService = $refundService;
        $this->logger = $logger;
    }

    /**
     * @param string $orderId
     * @param float $amount
     * @param string $description
     * @param Context $context
     * @return Refund
     */
    public function refundUsingOrderId(string $orderId, float $amount, string $description, Context $context): Refund
    {
        $order = $this->orderService->getOrder($orderId, $context);

        if (strlen(trim($description)) === 0) {
            $description = sprintf("Refunded through Shopware administration. Order number %s", $order->getOrderNumber());
        }

        $this->logger->info(sprintf('Refund for order %s with amount %s is triggered through the Shopware administration.', $order->getOrderNumber(), $amount));

        return $this->refund($order, $amount, $description, $context);
    }

    /**
     * @param string $orderNumber
     * @param float $amount
     * @param string $description
     * @param Context $context
     * @return Refund
     */
    public function refundUsingOrderNumber(string $orderNumber, float $amount, string $description, Context $context): Refund
    {
        $order = $this->orderService->getOrderByNumber($orderNumber, $context);

        if (strlen(trim($description)) === 0) {
            $description = sprintf("Refunded through Shopware API. Order number %s", $order->getOrderNumber());
        }

        $this->logger->info(sprintf('Refund for order %s with amount %s is triggered through the Shopware API.', $order->getOrderNumber(), $amount));

        return $this->refund($order, $amount, $description, $context);
    }

    /**
     * @param OrderEntity $order
     * @param float $amount
     * @param string $description
     * @param Context $context
     * @return Refund
     */
    private function refund(OrderEntity $order, float $amount, string $description, Context $context): Refund
    {
        if ($amount === 0.0) {
            $amount = $order->getAmountTotal() - $this->refundService->getRefundedAmount($order, $context);
        }

        return $this->refundService->refund($order, $amount, $description, $context);
    }

    /**
     * @param string $orderId
     * @param string $refundId
     * @param Context $context
     * @return bool
     */
    public function cancelUsingOrderId(string $orderId, string $refundId, Context $context): bool
    {
        $order = $this->orderService->getOrder($orderId, $context);

        $this->logger->info(sprintf('Refund with id %s for order %s was cancelled through the Shopware administration.', $refundId, $order->getOrderNumber()));

        return $this->refundService->cancel($order, $refundId, $context);
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return array<mixed>
     */
    public function getRefundListUsingOrderId(string $orderId, Context $context): array
    {
        $order = $this->orderService->getOrder($orderId, $context);

        return $this->refundService->getRefunds($order, $context);
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @return array<mixed>
     */
    public function getRefundTotalsUsingOrderId(string $orderId, Context $context): array
    {
        $order = $this->orderService->getOrder($orderId, $context);

        $remaining = $this->refundService->getRemainingAmount($order, $context);
        $refunded = $this->refundService->getRefundedAmount($order, $context);
        $voucherAmount = $this->refundService->getVoucherPaidAmount($order, $context);

        return [
            'remaining' => $remaining,
            'refunded' => $refunded,
            'voucherAmount' => $voucherAmount,
        ];
    }
}
