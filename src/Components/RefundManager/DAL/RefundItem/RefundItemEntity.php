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
    public function getLabel(): string
    {
        return (string) $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    /**
     * @return null|string
     */
    public function getOrderLineItemVersionId(): ?string
    {
        return $this->orderLineItemVersionId;
    }

    /**
     * @param null|string $orderLineItemVersionId
     */
    public function setOrderLineItemVersionId(?string $orderLineItemVersionId): void
    {
        $this->orderLineItemVersionId = $orderLineItemVersionId;
    }

    /**
     * @param string $mollieLineId
     * @param string $label
     * @param int $quantity
     * @param float $amount
     * @param null|string $oderLineItemId
     * @param null|string $oderLineItemVersionId
     * @param null|string $refundId
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

        /**
         * refundId is not given when we create a new entry because the id is created by shopware dal
         */
        if ($refundId !== null && $refundId !== '') {
            $row['refundId'] = $refundId;
        }

        return $row;
    }
}
