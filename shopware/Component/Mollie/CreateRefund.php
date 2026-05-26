<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class CreateRefund
{
    public function __construct(
        private readonly string $paymentId,
        private readonly Money $amount,
        private readonly string $description,
    ) {
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount' => [
                'value' => $this->amount->getValue(),
                'currency' => $this->amount->getCurrency(),
            ],
            'description' => $this->description,
        ];
    }
}
