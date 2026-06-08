<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class CreateOrderRefund extends CreateRefund
{
    public function __construct(
        private readonly string $orderId,
        private readonly LineItemCollection $lines,
    ) {
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getLines(): LineItemCollection
    {
        return $this->lines;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->description !== '') {
            $result['description'] = $this->description;
        }

        $lines = array_values(json_decode((string) json_encode($this->lines->getElements()), true) ?? []);
        if ($lines !== []) {
            $result['lines'] = array_map(function (array $line): array {
                return array_filter([
                    'id' => $line['id'] ?: null,
                    'quantity' => $line['quantity'],
                    'amount' => $line['amount'] ?? 0,
                ]);
            }, $lines);
        }

        if ($this->metadata !== []) {
            $result['metadata'] = $this->metadata;
        }

        return $result;
    }
}
