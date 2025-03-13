<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface OrderServiceInterface
{
    public function createOrder(DataBag $data, SalesChannelContext $context): OrderEntity;

    public function getOrder(string $orderId, Context $context): OrderEntity;

    public function getOrderByNumber(string $orderNumber, Context $context): OrderEntity;

    public function getMollieOrderId(OrderEntity $order): string;

    public function getMollieOrderLineId(OrderLineItemEntity $lineItem): string;
}
