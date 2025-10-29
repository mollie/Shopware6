<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class CreatePayment implements \JsonSerializable
{
    use JsonSerializableTrait;

    private string $description = '';
    private Money $amount;
    private string $redirectUrl = '';
    private string $cancelUrl = '';
    private string $webhookUrl = '';
    private string $method = '';
    private Address $billingAddress;
    private Address $shippingAddress;
    private ?string $captureMode = null;
    private string $locale;
    private LineItemCollection $lines;
    private string $sequenceType;

    private ?string $cardToken = null;

    public function __construct(string $description, string $redirectUrl, Money $amount)
    {
        $this->description = $description;
        $this->redirectUrl = $redirectUrl;
        $this->amount = $amount;
        $this->setSequenceType(new SequenceType());
    }

    public function getBillingAddress(): Address
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(Address $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }

    public function getShippingAddress(): Address
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(Address $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
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

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function getLines(): LineItemCollection
    {
        return $this->lines;
    }

    public function getLocale(): Locale
    {
        return new Locale($this->locale);
    }

    public function setLocale(Locale $locale): void
    {
        $this->locale = (string) $locale;
    }

    public function setSequenceType(SequenceType $sequenceType): void
    {
        $this->sequenceType = (string) $sequenceType;
    }

    public function getSequenceType(): SequenceType
    {
        return new SequenceType($this->sequenceType);
    }

    public function setLines(LineItemCollection $lines): void
    {
        $this->lines = $lines;
    }

    public function getCancelUrl(): string
    {
        return $this->cancelUrl;
    }

    public function setCancelUrl(string $cancelUrl): void
    {
        $this->cancelUrl = $cancelUrl;
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getCaptureMode(): ?CaptureMode
    {
        if ($this->captureMode === null) {
            return null;
        }

        return new CaptureMode($this->captureMode);
    }

    public function setCaptureMode(CaptureMode $captureMode): void
    {
        $this->captureMode = (string) $captureMode;
    }

    public function setCardToken(string $creditCardToken): void
    {
        $this->cardToken = $creditCardToken;
    }

    public function getCardToken(): ?string
    {
        return $this->cardToken;
    }

    public function toArray(): array
    {
        return json_decode(json_encode($this), true);
    }
}
