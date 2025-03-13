<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface RefundServiceInterface
{
    /**
     * @param RefundItem[] $refundItems
     */
    public function refundFull(OrderEntity $order, string $description, string $internalDescription, array $refundItems, Context $context): Refund;

    /**
     * @param RefundItem[] $lineItems
     *
     * @throws ApiException
     */
    public function refundPartial(OrderEntity $order, string $description, string $internalDescription, float $amount, array $lineItems, Context $context): Refund;

    public function cancel(OrderEntity $order, string $refundId): bool;

    /**
     * @return array<mixed>
     */
    public function getRefunds(OrderEntity $order, Context $context): array;

    public function getRemainingAmount(OrderEntity $order): float;

    public function getVoucherPaidAmount(OrderEntity $order): float;

    public function getRefundedAmount(OrderEntity $order): float;

    /**
     * @param array<mixed> $refunds
     */
    public function getPendingRefundAmount(array $refunds): float;
}
