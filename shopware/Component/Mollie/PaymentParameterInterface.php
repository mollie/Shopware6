<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

/**
 * Common payment-parameter surface shared by CreatePayment and CreateOrder.
 *
 * Handlers call these setters without knowing which API is in use.
 * Each payload struct places the values at the correct location:
 * - CreatePayment  → root level of the Payments-API payload
 * - CreateOrder    → inside the `payment` sub-array of the Orders-API payload
 */
interface PaymentParameterInterface
{
    public function setAuthenticationId(string $id): void;

    public function setCardToken(string $cardToken): void;

    public function setApplePayPaymentToken(string $token): void;

    public function setTerminalId(string $terminalId): void;

    public function setCustomerReference(string $customerReference): void;

    public function setSequenceType(SequenceType $sequenceType): void;

    public function getMandateId(): ?string;

    public function getBillingAddress(): Address;

    public function storeCredentials(): void;
}
