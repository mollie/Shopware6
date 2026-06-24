<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class CreatePaymentRefund extends CreateRefund
{
    public function __construct(
        private readonly string $paymentId,
        private readonly Money $amount,
        string $description = '',
    ) {
        $this->description = $description;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'amount' => $this->amount->toArray(),
            'description' => $this->description,
        ];

        if ($this->metadata !== []) {
            $data['metadata'] = $this->metadata;
        }

        return $data;
    }
}
