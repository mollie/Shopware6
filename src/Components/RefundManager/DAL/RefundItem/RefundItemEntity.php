<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class RefundItemEntity extends Entity
{
    use EntityIdTrait;

    protected string $type;
    protected string $refundId;
    protected string $mollieLineId;
    protected int $quantity;
    protected float $amount;
    protected ?string $lineItemId;
    protected ?OrderLineItemEntity $orderLineItem;

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getRefundId(): string
    {
        return $this->refundId;
    }

    /**
     * @param string $refundId
     */
    public function setRefundId(string $refundId): void
    {
        $this->refundId = $refundId;
    }

    /**
     * @return string
     */
    public function getMollieLineId(): string
    {
        return $this->mollieLineId;
    }

    /**
     * @param string $mollieLineId
     */
    public function setMollieLineId(string $mollieLineId): void
    {
        $this->mollieLineId = $mollieLineId;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     */
    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @param float $amount
     */
    public function setAmount(float $amount): void
    {
        $this->amount = $amount;
    }

    /**
     * @return string
     */
    public function getLineItemId(): ?string
    {
        return $this->lineItemId;
    }

    /**
     * @param string $lineItemId
     */
    public function setLineItemId(string $lineItemId): void
    {
        $this->lineItemId = $lineItemId;
    }

    /**
     * @return null|OrderLineItemEntity
     */
    public function getOrderLineItem(): ?OrderLineItemEntity
    {
        return $this->orderLineItem;
    }

    /**
     * @param OrderLineItemEntity $orderLineItem
     */
    public function setOrderLineItem(OrderLineItemEntity $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }
}
