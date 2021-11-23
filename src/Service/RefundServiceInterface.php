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
     * @param string|null $description
     * @param Context $context
     * @return Refund
     */
    public function refund(OrderEntity $order, float $amount, ?string $description, Context $context): Refund;

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param Context $context
     * @return Refund
     */
    public function refundFullOrder(OrderEntity $order, string $description, Context $context): Refund;

    /**
     * @param OrderEntity $order
     * @param string $refundId
     * @param Context $context
     * @return bool
     */
    public function cancel(OrderEntity $order, string $refundId, Context $context): bool;

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return array
     */
    public function getRefunds(OrderEntity $order, Context $context): array;

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return float
     */
    public function getRemainingAmount(OrderEntity $order, Context $context): float;

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return float
     */
    public function getVoucherPaidAmount(OrderEntity $order, Context $context): float;

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return float
     */
    public function getRefundedAmount(OrderEntity $order, Context $context): float;

}
