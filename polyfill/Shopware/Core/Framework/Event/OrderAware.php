<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

interface OrderAware extends BusinessEventInterface
{
    public const ORDER = 'order';

    public const ORDER_ID = 'orderId';

    public function getOrderId(): string;
}
