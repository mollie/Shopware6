<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Refund;

use Kiener\MolliePayments\Service\Refund\Item\MollieRefundItem;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface RefundServiceInterface
{

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param Context $context
     * @return Refund
     */
    public function refundFull(OrderEntity $order, string $description, Context $context): Refund;

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param float $amount
     * @param MollieRefundItem[] $lineItems
     * @param Context $context
     * @return Refund
     * @throws ApiException
     */
    public function refundAmount(OrderEntity $order, string $description, float $amount, array $lineItems, Context $context): Refund;

    /**
     * @param OrderEntity $order
     * @param string $description
     * @param MollieRefundItem[] $refundItems
     * @param Context $context
     * @return Refund
     * @throws ApiException
     */
    public function refundItems(OrderEntity $order, string $description, array $refundItems, Context $context): Refund;

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
