<?php

namespace Kiener\MolliePayments\Components\RefundManager\Integrators;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

interface StockManagerInterface
{
    /**
     * @param OrderLineItemEntity $lineItem
     * @param int $quantity
     * @param string $mollieRefundID
     * @return void
     */
    public function increaseStock(OrderLineItemEntity $lineItem, int $quantity, string $mollieRefundID): void;
}
