<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class RefundItem
{
    public function __construct(
        private readonly string $id,
        private readonly int $quantity,
        private readonly ?Money $amount = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'quantity' => $this->quantity,
        ];

        if ($this->amount !== null) {
            $data['amount'] = $this->amount->toArray();
        }

        return $data;
    }
}
