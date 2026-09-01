<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class ShippingItem implements \JsonSerializable
{
    public function __construct(
        private int $quantity,
        private float $amount,
        private ?string $mollieLineId = null,
    ) {
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getMollieLineId(): ?string
    {
        return $this->mollieLineId;
    }

    /** @return array{id: string, quantity: int} */
    public function jsonSerialize(): array
    {
        return [
            'id' => (string) $this->mollieLineId,
            'quantity' => $this->quantity,
        ];
    }
}
