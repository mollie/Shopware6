<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Mandate;

use Shopware\Core\Framework\Struct\Struct;

class CreditCardDetailStruct extends Struct
{
    /**
     * @var null|string
     */
    protected $cardHolder;

    /**
     * @var null|string
     */
    protected $cardNumber;

    /**
     * @var null|string
     */
    protected $cardLabel;

    /**
     * @var null|string
     */
    protected $cardFingerprint;

    /**
     * @var null|string
     */
    protected $cardExpiryDate;

    public function getCardHolder(): ?string
    {
        return $this->cardHolder;
    }

    public function setCardHolder(?string $cardHolder): void
    {
        $this->cardHolder = $cardHolder;
    }

    public function getCardNumber(): ?string
    {
        return $this->cardNumber;
    }

    public function setCardNumber(?string $cardNumber): void
    {
        $this->cardNumber = $cardNumber;
    }

    public function getCardLabel(): ?string
    {
        return $this->cardLabel;
    }

    public function setCardLabel(?string $cardLabel): void
    {
        $this->cardLabel = $cardLabel;
    }

    public function getCardFingerprint(): ?string
    {
        return $this->cardFingerprint;
    }

    public function setCardFingerprint(?string $cardFingerprint): void
    {
        $this->cardFingerprint = $cardFingerprint;
    }

    public function getCardExpiryDate(): ?string
    {
        return $this->cardExpiryDate;
    }

    public function setCardExpiryDate(?string $cardExpiryDate): void
    {
        $this->cardExpiryDate = $cardExpiryDate;
    }
}
