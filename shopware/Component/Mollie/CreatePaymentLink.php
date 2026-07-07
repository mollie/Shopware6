<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

/**
 * Payload DTO for POST /v2/payment-links.
 *
 * Payment links are created on demand when a customer opens the pay URL of an order.
 * They are intentionally not reusable and have no expiry date.
 *
 * @see https://docs.mollie.com/reference/create-payment-link
 */
final class CreatePaymentLink
{
    private string $redirectUrl = '';
    private string $webhookUrl = '';
    private ?Address $billingAddress = null;
    private ?Address $shippingAddress = null;
    private ?LineItemCollection $lines = null;
    private SequenceType $sequenceType = SequenceType::ONEOFF;
    private ?string $customerId = null;
    /**
     * @var string[]
     */
    private array $allowedMethods = [];

    public function __construct(
        private readonly string $description,
        private readonly Money $amount,
    ) {
    }

    /**
     * Derives a payment link payload from an already built payment payload so the shared
     * fields (addresses, lines, urls) only have to be assembled once in the PayloadBuilder.
     * The allowed methods are intentionally not copied and are set by the builder afterwards.
     */
    public static function fromCreatePayment(CreatePayment $payment): self
    {
        $paymentLink = new self($payment->getDescription(), $payment->getAmount());
        $paymentLink->setRedirectUrl($payment->getRedirectUrl());
        $paymentLink->setWebhookUrl($payment->getWebhookUrl());
        $paymentLink->setBillingAddress($payment->getBillingAddress());
        $paymentLink->setShippingAddress($payment->getShippingAddress());
        $paymentLink->setLines($payment->getLines());

        return $paymentLink;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function setRedirectUrl(string $redirectUrl): void
    {
        $this->redirectUrl = $redirectUrl;
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    public function getBillingAddress(): ?Address
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(Address $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }

    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(Address $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    public function getLines(): ?LineItemCollection
    {
        return $this->lines;
    }

    public function setLines(LineItemCollection $lines): void
    {
        $this->lines = $lines;
    }

    public function getSequenceType(): SequenceType
    {
        return $this->sequenceType;
    }

    public function setSequenceType(SequenceType $sequenceType): void
    {
        $this->sequenceType = $sequenceType;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /**
     * @param string[] $allowedMethods
     */
    public function setAllowedMethods(array $allowedMethods): void
    {
        $this->allowedMethods = array_values($allowedMethods);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'description' => $this->description,
            'amount' => $this->amount->toArray(),
            'reusable' => false,
        ];

        if ($this->redirectUrl !== '') {
            $data['redirectUrl'] = $this->redirectUrl;
        }
        if ($this->webhookUrl !== '') {
            $data['webhookUrl'] = $this->webhookUrl;
        }
        if ($this->lines !== null && $this->lines->count() > 0) {
            $data['lines'] = json_decode((string) json_encode($this->lines), true);
        }
        if ($this->billingAddress !== null) {
            $data['billingAddress'] = json_decode((string) json_encode($this->billingAddress), true);
        }
        if ($this->shippingAddress !== null) {
            $data['shippingAddress'] = json_decode((string) json_encode($this->shippingAddress), true);
        }
        if (count($this->allowedMethods) > 0) {
            $data['allowedMethods'] = $this->allowedMethods;
        }
        $data['sequenceType'] = $this->sequenceType->value;
        if ($this->customerId !== null && $this->customerId !== '') {
            $data['customerId'] = $this->customerId;
        }

        return $data;
    }
}
