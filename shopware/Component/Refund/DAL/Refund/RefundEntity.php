<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Refund\DAL\Refund;

use Mollie\Shopware\Component\Refund\DAL\RefundItem\RefundItemCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

final class RefundEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $orderId = null;
    protected ?string $mollieRefundId = null;
    protected ?string $type = null;
    protected ?string $publicDescription = null;
    protected ?string $internalDescription = null;
    protected RefundItemCollection $refundItems;

    public function __construct()
    {
        $this->refundItems = new RefundItemCollection();
    }

    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getMollieRefundId(): ?string
    {
        return $this->mollieRefundId;
    }

    public function setMollieRefundId(?string $mollieRefundId): void
    {
        $this->mollieRefundId = $mollieRefundId;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getPublicDescription(): ?string
    {
        return $this->publicDescription;
    }

    public function setPublicDescription(?string $publicDescription): void
    {
        $this->publicDescription = $publicDescription;
    }

    public function getInternalDescription(): ?string
    {
        return $this->internalDescription;
    }

    public function setInternalDescription(?string $internalDescription): void
    {
        $this->internalDescription = $internalDescription;
    }

    public function getRefundItems(): RefundItemCollection
    {
        return $this->refundItems;
    }

    public function setRefundItems(RefundItemCollection $refundItems): void
    {
        $this->refundItems = $refundItems;
    }
}
