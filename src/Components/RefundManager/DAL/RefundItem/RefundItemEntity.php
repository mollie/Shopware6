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
    protected $type;

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
    protected $reference;
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
    public function getOrderLineItemId(): ?string
    {
        return $this->orderLineItemId;
    }

    /**
     * @param string $orderLineItemId
     */
    public function setOrderLineItemId(string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
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

    /**
     * @return null|RefundEntity
     */
    public function getRefund(): ?RefundEntity
    {
        return $this->refund;
    }

    /**
     * @param null|RefundEntity $refund
     */
    public function setRefund(?RefundEntity $refund): void
    {
        $this->refund = $refund;
    }

    /**
     * @return string
     */
    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @param string $reference
     */
    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    /**
     * @return null|string
     */
    public function getOrderLineItemVersionId(): ?string
    {
        return $this->orderLineItemVersionId;
    }
}
