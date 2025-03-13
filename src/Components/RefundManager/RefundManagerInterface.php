<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager;

use Kiener\MolliePayments\Components\RefundManager\RefundData\RefundData;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Mollie\Api\Resources\Refund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

interface RefundManagerInterface
{
    public function getData(OrderEntity $order, Context $context): RefundData;

    public function refund(OrderEntity $order, RefundRequest $request, Context $context): Refund;

    public function cancelAllOrderRefunds(OrderEntity $order, Context $context): bool;

    public function cancelRefund(string $orderId, string $refundId, Context $context): bool;
}
