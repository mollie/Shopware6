<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem;

use Kiener\MolliePayments\Components\RefundManager\DAL\Refund\RefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class RefundItemEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $refundId;

    /**
     * @var null|RefundEntity
     */
    protected $refund;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $mollieLineId;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var float
     */
    protected $amount;

    /**
     * @var null|string
     */
    protected $orderLineItemId;

    /**
     * @var null|string
     */
    protected $orderLineItemVersionId;

    /**
     * @var null|OrderLineItemEntity
     */
    protected $orderLineItem;

    public function getRefundId(): string
    {
        return $this->refundId;
    }

    public function setRefundId(string $refundId): void
    {
        $this->refundId = $refundId;
    }

    public function getMollieLineId(): string
    {
        return $this->mollieLineId;
    }

    public function setMollieLineId(string $mollieLineId): void
    {
        $this->mollieLineId = $mollieLineId;
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

    /**
     * @return string
     */
    public function getOrderLineItemId(): ?string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
    }

    public function getOrderLineItem(): ?OrderLineItemEntity
    {
        return $this->orderLineItem;
    }

    public function setOrderLineItem(OrderLineItemEntity $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }

    public function getRefund(): ?RefundEntity
    {
        return $this->refund;
    }

    public function setRefund(?RefundEntity $refund): void
    {
        $this->refund = $refund;
    }

    public function getLabel(): string
    {
        return (string) $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getOrderLineItemVersionId(): ?string
    {
        return $this->orderLineItemVersionId;
    }

    public function setOrderLineItemVersionId(?string $orderLineItemVersionId): void
    {
        $this->orderLineItemVersionId = $orderLineItemVersionId;
    }

    /**
     * @return array<mixed>
     */
    public static function createArray(string $mollieLineId, string $label, int $quantity, float $amount, ?string $oderLineItemId, ?string $oderLineItemVersionId, ?string $refundId): array
    {
        $row = [
            'mollieLineId' => $mollieLineId,
            'label' => $label,
            'quantity' => $quantity,
            'amount' => $amount,
            'orderLineItemId' => $oderLineItemId,
            'orderLineItemVersionId' => $oderLineItemVersionId,
        ];

        /*
         * refundId is not given when we create a new entry because the id is created by shopware dal
         */
        if ($refundId !== null && $refundId !== '') {
            $row['refundId'] = $refundId;
        }

        return $row;
    }
}
