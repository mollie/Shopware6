<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Mandate;

use Shopware\Core\Framework\Struct\Struct;

class MandateStruct extends Struct
{
    /**
     * @var null|string
     */
    protected $resource;

    /**
     * @var null|string
     */
    protected $id;

    /**
     * @var null|string
     */
    protected $mode;

    /**
     * @var null|string
     */
    protected $status;

    /**
     * @var null|string
     */
    protected $method;

    /**
     * @var CreditCardDetailStruct
     */
    protected $details;

    /**
     * @var null|string
     */
    protected $customerId;

    /**
     * @var null|string
     */
    protected $mandateReference;

    /**
     * @var null|string
     */
    protected $signatureDate;

    /**
     * @var null|string
     */
    protected $createdAt;

    /**
     * @var bool
     */
    protected $beingUsedForSubscription = false;

    public function getResource(): ?string
    {
        return $this->resource;
    }

    public function setResource(?string $resource): void
    {
        $this->resource = $resource;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getMode(): ?string
    {
        return $this->mode;
    }

    public function setMode(?string $mode): void
    {
        $this->mode = $mode;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(?string $method): void
    {
        $this->method = $method;
    }

    public function getDetails(): CreditCardDetailStruct
    {
        return $this->details;
    }

    public function setDetails(CreditCardDetailStruct $details): void
    {
        $this->details = $details;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getMandateReference(): ?string
    {
        return $this->mandateReference;
    }

    public function setMandateReference(?string $mandateReference): void
    {
        $this->mandateReference = $mandateReference;
    }

    public function getSignatureDate(): ?string
    {
        return $this->signatureDate;
    }

    public function setSignatureDate(?string $signatureDate): void
    {
        $this->signatureDate = $signatureDate;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function isBeingUsedForSubscription(): bool
    {
        return $this->beingUsedForSubscription;
    }

    public function setBeingUsedForSubscription(bool $beingUsedForSubscription): void
    {
        $this->beingUsedForSubscription = $beingUsedForSubscription;
    }
}
