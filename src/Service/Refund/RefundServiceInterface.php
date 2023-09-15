<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface RefundServiceInterface
{
    /**
     * @param OrderEntity $order
     * @param string $description
     * @param string $internalDescription
     * @param RefundItem[] $refundItems
     * @param Context $context
     * @return Refund
     */
    public function refundFull(OrderEntity $order, string $description, string $internalDescription, array $refundItems, Context $context): Refund;

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param string $internalDescription
     * @param float $amount
     * @param RefundItem[] $lineItems
     * @param Context $context
     * @throws ApiException
     * @return Refund
     */
    public function refundPartial(OrderEntity $order, string $description, string $internalDescription, float $amount, array $lineItems, Context $context): Refund;

    /**
     * @param OrderEntity $order
     * @param string $refundId
     * @return bool
     */
    public function cancel(OrderEntity $order, string $refundId): bool;

    /**
     * @param OrderEntity $order
     * @return Refund[]
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
