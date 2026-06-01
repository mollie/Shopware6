<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Event;

use Mollie\Shopware\Component\Mollie\CreateRefund;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class ModifyCreateRefundPayloadEvent
{
    public function __construct(
        private CreateRefund $createRefund,
        private readonly OrderEntity $order,
        private readonly Context $context,
    ) {
    }

    public function getCreateRefund(): CreateRefund
    {
        return $this->createRefund;
    }

    public function setCreateRefund(CreateRefund $createRefund): void
    {
        $this->createRefund = $createRefund;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
