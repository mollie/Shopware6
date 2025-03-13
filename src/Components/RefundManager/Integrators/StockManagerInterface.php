<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\Integrators;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

interface StockManagerInterface
{
    public function increaseStock(OrderLineItemEntity $lineItem, int $quantity): void;
}
