<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Components\Subscription\DAL\Refund;

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
     * @var string|null
     */
    protected $orderId;

    /**
     * @var string|null
     */
    protected $mollieRefundId;

    /**
     * @var string|null
     */
    protected $publicDescription;

    /**
     * @var string|null
     */
    protected $internalDescription;

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
     * @return string|null
     */
    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    /**
     * @param string|null $orderId
     * @return void
     */
    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * @return string|null
     */
    public function getMollieRefundId(): ?string
    {
        return $this->mollieRefundId;
    }

    /**
     * @param string|null $mollieRefundId
     * @return void
     */
    public function setMollieRefundId(?string $mollieRefundId): void
    {
        $this->mollieRefundId = $mollieRefundId;
    }

    /**
     * @return string|null
     */
    public function getPublicDescription(): ?string
    {
        return $this->publicDescription;
    }

    /**
     * @param string|null $publicDescription
     * @return void
     */
    public function setPublicDescription(?string $publicDescription): void
    {
        $this->publicDescription = $publicDescription;
    }

    /**
     * @return string|null
     */
    public function getInternalDescription(): ?string
    {
        return $this->internalDescription;
    }

    /**
     * @param string|null $internalDescription
     * @return void
     */
    public function setInternalDescription(?string $internalDescription): void
    {
        $this->internalDescription = $internalDescription;
    }
}
