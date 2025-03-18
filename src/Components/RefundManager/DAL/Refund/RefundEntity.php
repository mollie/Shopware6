<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\Refund;

use Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem\RefundItemCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class RefundEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var null|string
     */
    protected $orderId;

    /**
     * @var null|string
     */
    protected $mollieRefundId;

    /**
     * @var null|string
     */
    protected $type;

    /**
     * @var null|string
     */
    protected $publicDescription;

    /**
     * @var null|string
     */
    protected $internalDescription;

    /**
     * @var RefundItemCollection
     */
    protected $refundItems;

    public function __construct()
    {
        $this->refundItems = new RefundItemCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
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
