<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\Return\Struct;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

/**
 * A return of the Return Management, reduced to what the refund needs.
 *
 * SwagCommercial is a paid plugin and not part of the plugin's own vendor/, so its
 * OrderReturnEntity cannot be loaded outside a shop that has it installed. Keeping the entity out
 * of the actions is what makes them testable at all - only OrderReturnLoader still touches the
 * Commercial classes, and it maps them onto this type.
 *
 * The order stays nullable: a return without one is an error AbstractReturnAction logs, and moving
 * that decision into the mapper would lose the message.
 */
final class OrderReturnStruct extends Struct
{
    use JsonSerializableTrait;

    /**
     * @param OrderReturnLineItemStruct[] $lineItems
     */
    public function __construct(
        private string $id,
        private ?string $state,
        private ?OrderEntity $order,
        private ?float $amountTotal,
        private string $internalComment,
        private float $shippingCostsTotal,
        private array $lineItems,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Technical name of the state the return is in, e.g. "done" or "cancelled".
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    /**
     * Only written once the return was recalculated, so the handler falls back to the positions.
     */
    public function getAmountTotal(): ?float
    {
        return $this->amountTotal;
    }

    public function getInternalComment(): string
    {
        return $this->internalComment;
    }

    public function getShippingCostsTotal(): float
    {
        return $this->shippingCostsTotal;
    }

    /**
     * @return OrderReturnLineItemStruct[]
     */
    public function getLineItems(): array
    {
        return $this->lineItems;
    }
}
