<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Refund\DAL\RefundItem\RefundItemCollection;

final class Refund implements \JsonSerializable
{
    private ?RefundItemCollection $refundItems = null;
    private string $internalDescription = '';

    public function __construct(
        private string $id,
        private string $paymentId,
        private RefundStatus $status,
        private Money $amount,
        private string $description,
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function setRefundItems(RefundItemCollection $items): void
    {
        $this->refundItems = $items;
    }

    public function setInternalDescription(string $internalDescription): void
    {
        $this->internalDescription = $internalDescription;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'paymentId' => $this->paymentId,
            'amount' => $this->amount,
            'description' => $this->description,
            'status' => $this->status->value,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
            'isPending' => $this->status === RefundStatus::Pending,
            'isQueued' => $this->status === RefundStatus::Queued,
            'internalDescription' => $this->internalDescription,
            'metadata' => ['composition' => $this->refundItems ?? []],
        ];
    }

    /**
     * @param array<mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        return new self(
            $body['id'],
            $body['paymentId'],
            RefundStatus::from($body['status']),
            new Money((float) $body['amount']['value'], $body['amount']['currency']),
            $body['description'] ?? '',
            new \DateTimeImmutable($body['createdAt']),
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getStatus(): RefundStatus
    {
        return $this->status;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
