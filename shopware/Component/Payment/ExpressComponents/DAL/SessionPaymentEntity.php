<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents\DAL;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * Links a Mollie session to the payment Mollie created for it and to the Shopware transaction
 * of the order. The id is the one in the session webhook url, Mollie does not send the session id.
 */
final class SessionPaymentEntity extends Entity
{
    use EntityIdTrait;

    protected string $sessionId = '';
    protected ?string $paymentId = null;
    protected ?string $transactionId = null;

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    public function setPaymentId(?string $paymentId): void
    {
        $this->paymentId = $paymentId;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function setTransactionId(?string $transactionId): void
    {
        $this->transactionId = $transactionId;
    }
}
