<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

/**
 * Links a Mollie session to the payment Mollie created for it and to the Shopware transaction
 * of the order. The id is the one in the session webhook url, Mollie does not send the session id.
 */
final class SessionPayment
{
    public function __construct(
        private string $id,
        private string $sessionId,
        private ?string $paymentId,
        private ?string $transactionId
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getPaymentId(): ?string
    {
        return $this->paymentId;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }
}
