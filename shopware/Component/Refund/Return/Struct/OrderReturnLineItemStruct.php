<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Return\Struct;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * One returned position, mapped off the Return Management of SwagCommercial. See
 * OrderReturnStruct for why the plugin carries its own type here.
 */
final class OrderReturnLineItemStruct extends Struct
{
    use JsonSerializableTrait;

    public function __construct(
        private string $orderLineItemId,
        private int $quantity,
        private float $refundAmount,
        private int $restockQuantity,
    ) {
    }

    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getRefundAmount(): float
    {
        return $this->refundAmount;
    }

    public function getRestockQuantity(): int
    {
        return $this->restockQuantity;
    }
}
