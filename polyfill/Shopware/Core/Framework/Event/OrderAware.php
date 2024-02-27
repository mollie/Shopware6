<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\Event;


if (interface_exists(__NAMESPACE__.'/OrderAware')) {
    return;
}

interface OrderAware extends FlowEventAware
{
    public const ORDER = 'order';

    public const ORDER_ID = 'orderId';

    public function getOrderId(): string;
}
