<?php

namespace Kiener\MolliePayments\Components\RefundManager;

use Kiener\MolliePayments\Components\RefundManager\RefundData\RefundData;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface RefundManagerInterface
{
    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return RefundData
     */
    public function getData(OrderEntity $order, Context $context): RefundData;

    /**
     * @param OrderEntity $order
     * @param RefundRequest $request
     * @param Context $context
     * @return Refund
     */
    public function refund(OrderEntity $order, RefundRequest $request, Context $context): Refund;

    /**
     * @param string $orderId
     * @param string $refundId
     * @param Context $context
     * @return bool
     */
    public function cancelRefund(string $orderId, string $refundId, Context $context): bool;
}
