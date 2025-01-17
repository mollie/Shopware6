<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\RefundManager\DAL\Refund;

use Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem\RefundItemCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

;

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


    /**
     *
     */
    public function __construct()
    {
        $this->refundItems = new RefundItemCollection();
    }


    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return void
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return null|string
     */
    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    /**
     * @param null|string $orderId
     * @return void
     */
    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * @return null|string
     */
    public function getMollieRefundId(): ?string
    {
        return $this->mollieRefundId;
    }

    /**
     * @param null|string $mollieRefundId
     * @return void
     */
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

    /**
     * @return null|string
     */
    public function getPublicDescription(): ?string
    {
        return $this->publicDescription;
    }

    /**
     * @param null|string $publicDescription
     * @return void
     */
    public function setPublicDescription(?string $publicDescription): void
    {
        $this->publicDescription = $publicDescription;
    }

    /**
     * @return null|string
     */
    public function getInternalDescription(): ?string
    {
        return $this->internalDescription;
    }

    /**
     * @param null|string $internalDescription
     * @return void
     */
    public function setInternalDescription(?string $internalDescription): void
    {
        $this->internalDescription = $internalDescription;
    }

    /**
     * @return RefundItemCollection
     */
    public function getRefundItems(): RefundItemCollection
    {
        return $this->refundItems;
    }

    /**
     * @param RefundItemCollection $refundItems
     */
    public function setRefundItems(RefundItemCollection $refundItems): void
    {
        $this->refundItems = $refundItems;
    }
}
