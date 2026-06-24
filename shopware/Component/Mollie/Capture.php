<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class Capture
{
    public function __construct(
        private string $id,
        private CaptureStatus $status,
        private Money $amount
    ) {
    }

    /**
     * @param array<mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        $id = $body['id'];
        $status = CaptureStatus::from($body['status']);

        $amount = new Money((float) $body['amount']['value'],$body['amount']['currency']);

        return new self($id, $status, $amount);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): CaptureStatus
    {
        return $this->status;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function setStatus(CaptureStatus $status): void
    {
        $this->status = $status;
    }
}
