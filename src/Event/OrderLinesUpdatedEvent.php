<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Event;

use Mollie\Api\Resources\Order;

class OrderLinesUpdatedEvent
{
    private Order $mollieOrder;

    public function __construct(Order $mollieOrder)
    {
        $this->mollieOrder = $mollieOrder;
    }

    public function getMollieOrder(): Order
    {
        return $this->mollieOrder;
    }
}
