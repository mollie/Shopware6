<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct\Mandate;

use Shopware\Core\Framework\Struct\Struct;

class CreditCardDetailStruct extends Struct
{
    /**
     * @var null|string
     */
    protected $cardHolder = null;

    /**
     * @var null|string
     */
    protected $cardNumber = null;

    /**
     * @var null|string
     */
    protected $cardLabel = null;

    /**
     * @var null|string
     */
    protected $cardFingerprint = null;

    /**
     * @var null|string
     */
    protected $cardExpiryDate = null;

    /**
     * @return null|string
     */
    public function getCardHolder(): ?string
    {
        return $this->cardHolder;
    }

    /**
     * @param null|string $cardHolder
     */
    public function setCardHolder(?string $cardHolder): void
    {
        $this->cardHolder = $cardHolder;
    }

    /**
     * @return null|string
     */
    public function getCardNumber(): ?string
    {
        return $this->cardNumber;
    }

    /**
     * @param null|string $cardNumber
     */
    public function setCardNumber(?string $cardNumber): void
    {
        $this->cardNumber = $cardNumber;
    }

    /**
     * @return null|string
     */
    public function getCardLabel(): ?string
    {
        return $this->cardLabel;
    }

    /**
     * @param null|string $cardLabel
     */
    public function setCardLabel(?string $cardLabel): void
    {
        $this->cardLabel = $cardLabel;
    }

    /**
     * @return null|string
     */
    public function getCardFingerprint(): ?string
    {
        return $this->cardFingerprint;
    }

    /**
     * @param null|string $cardFingerprint
     */
    public function setCardFingerprint(?string $cardFingerprint): void
    {
        $this->cardFingerprint = $cardFingerprint;
    }

    /**
     * @return null|string
     */
    public function getCardExpiryDate(): ?string
    {
        return $this->cardExpiryDate;
    }

    /**
     * @param null|string $cardExpiryDate
     */
    public function setCardExpiryDate(?string $cardExpiryDate): void
    {
        $this->cardExpiryDate = $cardExpiryDate;
    }
}
