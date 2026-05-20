<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\Event;

use Mollie\Shopware\Component\Mollie\CreateOrder;
use Shopware\Core\Framework\Context;

final class ModifyCreateOrderPayloadEvent
{
    public function __construct(private CreateOrder $order, private Context $context)
    {
    }

    public function getOrder(): CreateOrder
    {
        return $this->order;
    }

    public function setOrder(CreateOrder $order): void
    {
        $this->order = $order;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
