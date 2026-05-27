<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\DAL\RefundItem;

use Mollie\Shopware\Component\Refund\DAL\Refund\RefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class RefundItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $refundId = '';
    protected ?RefundEntity $refund = null;
    protected string $mollieLineId = '';
    protected string $label = '';
    protected int $quantity = 0;
    protected float $amount = 0.0;
    protected ?string $orderLineItemId = null;
    protected ?string $orderLineItemVersionId = null;
    protected ?OrderLineItemEntity $orderLineItem = null;

    public function getRefundId(): string
    {
        return $this->refundId;
    }

    public function setRefundId(string $refundId): void
    {
        $this->refundId = $refundId;
    }

    public function getRefund(): ?RefundEntity
    {
        return $this->refund;
    }

    public function setRefund(?RefundEntity $refund): void
    {
        $this->refund = $refund;
    }

    public function getMollieLineId(): string
    {
        return $this->mollieLineId;
    }

    public function setMollieLineId(string $mollieLineId): void
    {
        $this->mollieLineId = $mollieLineId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    public function getOrderLineItemId(): ?string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(?string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
    }

    public function getOrderLineItemVersionId(): ?string
    {
        return $this->orderLineItemVersionId;
    }

    public function setOrderLineItemVersionId(?string $orderLineItemVersionId): void
    {
        $this->orderLineItemVersionId = $orderLineItemVersionId;
    }

    public function getOrderLineItem(): ?OrderLineItemEntity
    {
        return $this->orderLineItem;
    }

    public function setOrderLineItem(?OrderLineItemEntity $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'swReference' => $this->label,
            'label' => $this->label,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
            'orderLineItemId' => $this->orderLineItemId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function createArray(
        string $mollieLineId,
        string $label,
        int $quantity,
        float $amount,
        ?string $orderLineItemId,
        ?string $orderLineItemVersionId,
        ?string $refundId = null,
    ): array {
        $row = [
            'mollieLineId' => $mollieLineId,
            'label' => $label,
            'quantity' => $quantity,
            'amount' => $amount,
            'orderLineItemId' => $orderLineItemId,
            'orderLineItemVersionId' => $orderLineItemVersionId,
        ];

        if ($refundId !== null && $refundId !== '') {
            $row['refundId'] = $refundId;
        }

        return $row;
    }
}
