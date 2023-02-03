<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Mandate;

use Shopware\Core\Framework\Struct\Struct;

class MandateStruct extends Struct
{
    /**
     * @var null|string
     */
    protected $resource = null;

    /**
     * @var null|string
     */
    protected $id = null;

    /**
     * @var null|string
     */
    protected $mode = null;

    /**
     * @var null|string
     */
    protected $status = null;

    /**
     * @var null|string
     */
    protected $method = null;

    /**
     * @var CreditCardDetailStruct
     */
    protected $details;

    /**
     * @var null|string
     */
    protected $customerId = null;

    /**
     * @var null|string
     */
    protected $mandateReference = null;

    /**
     * @var null|string
     */
    protected $signatureDate = null;

    /**
     * @var null|string
     */
    protected $createdAt = null;

    /**
     * @var bool
     */
    protected $beingUsedForSubscription = false;

    /**
     * @return null|string
     */
    public function getResource(): ?string
    {
        return $this->resource;
    }

    /**
     * @param null|string $resource
     */
    public function setResource(?string $resource): void
    {
        $this->resource = $resource;
    }

    /**
     * @return null|string
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * @param null|string $id
     */
    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    /**
     * @return null|string
     */
    public function getMode(): ?string
    {
        return $this->mode;
    }

    /**
     * @param null|string $mode
     */
    public function setMode(?string $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * @return null|string
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * @param null|string $status
     */
    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return null|string
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @param null|string $method
     */
    public function setMethod(?string $method): void
    {
        $this->method = $method;
    }

    /**
     * @return CreditCardDetailStruct
     */
    public function getDetails(): CreditCardDetailStruct
    {
        return $this->details;
    }

    /**
     * @param CreditCardDetailStruct $details
     */
    public function setDetails(CreditCardDetailStruct $details): void
    {
        $this->details = $details;
    }

    /**
     * @return null|string
     */
    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    /**
     * @param null|string $customerId
     */
    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return null|string
     */
    public function getMandateReference(): ?string
    {
        return $this->mandateReference;
    }

    /**
     * @param null|string $mandateReference
     */
    public function setMandateReference(?string $mandateReference): void
    {
        $this->mandateReference = $mandateReference;
    }

    /**
     * @return null|string
     */
    public function getSignatureDate(): ?string
    {
        return $this->signatureDate;
    }

    /**
     * @param null|string $signatureDate
     */
    public function setSignatureDate(?string $signatureDate): void
    {
        $this->signatureDate = $signatureDate;
    }

    /**
     * @return null|string
     */
    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    /**
     * @param null|string $createdAt
     */
    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * @return bool
     */
    public function isBeingUsedForSubscription(): bool
    {
        return $this->beingUsedForSubscription;
    }

    /**
     * @param bool $beingUsedForSubscription
     */
    public function setBeingUsedForSubscription(bool $beingUsedForSubscription): void
    {
        $this->beingUsedForSubscription = $beingUsedForSubscription;
    }
}
