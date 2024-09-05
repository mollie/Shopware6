<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\Api\PluginConfig\Structs;

use Shopware\Core\Framework\Struct\Struct;

class OrderLineItemStruct extends Struct
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var bool
     */
    private $isShipped = false;

    /**
     * @var int
     */
    private $refundedCount = 0;

    /**
     * @var bool
     */
    private $hasPendingRefund = false;

    /**
     * @var int
     */
    private $orderedQuantity = 0;

    final private function __construct(string $id)
    {
        $this->id = $id;
    }

    public static function createWithId(string $id): self
    {
        return new self($id);
    }

    public function isShipped(): bool
    {
        return $this->isShipped;
    }

    public function setIsShipped(bool $isShipped): OrderLineItemStruct
    {
        $this->isShipped = $isShipped;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderedQuantity(): int
    {
        return $this->orderedQuantity;
    }

    public function setOrderedQuantity(int $orderedQuantity): OrderLineItemStruct
    {
        $this->orderedQuantity = $orderedQuantity;
        return $this;
    }

    public function getRefundedCount(): int
    {
        return $this->refundedCount;
    }

    public function hasPendingRefund(): bool
    {
        return $this->hasPendingRefund;
    }

    public function setHasPendingRefund(bool $hasPendingRefund): OrderLineItemStruct
    {
        $this->hasPendingRefund = $hasPendingRefund;
        return $this;
    }

    public function setRefundedCount(int $refundedCount): OrderLineItemStruct
    {
        $this->refundedCount = $refundedCount;
        return $this;
    }
}
