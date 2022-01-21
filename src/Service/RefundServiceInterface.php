<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface RefundServiceInterface
{
    /**
     * @param OrderEntity $order
     * @param float $amount
     * @param string $description
     * @return Refund
     */
    public function refund(OrderEntity $order, float $amount, string $description): Refund;

    /**
     * @param OrderEntity $order
     * @param string $description
     * @return Refund
     */
    public function refundFullOrder(OrderEntity $order, string $description): Refund;

    /**
     * @param OrderEntity $order
     * @param string $refundId
     * @return bool
     */
    public function cancel(OrderEntity $order, string $refundId): bool;

    /**
     * @param OrderEntity $order
     * @return array
     */
    public function getRefunds(OrderEntity $order): array;

    /**
     * @param OrderEntity $order
     * @return float
     */
    public function getRemainingAmount(OrderEntity $order): float;

    /**
     * @param OrderEntity $order
     * @return float
     */
    public function getVoucherPaidAmount(OrderEntity $order): float;

    /**
     * @param OrderEntity $order
     * @return float
     */
    public function getRefundedAmount(OrderEntity $order): float;
}
