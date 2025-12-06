<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class CreatePayment implements \JsonSerializable
{
    use JsonSerializableTrait;

    private string $cancelUrl = '';
    private string $webhookUrl = '';
    private PaymentMethod $method;
    private Address $billingAddress;
    private Address $shippingAddress;
    private ?CaptureMode $captureMode = null;
    private Locale $locale;
    private LineItemCollection $lines;
    private SequenceType $sequenceType;

    private ?string $cardToken = null;
    private ?string $applePayPaymentToken = null;

    private ?string $customerReference = null;
    private ?string $customerId = null;
    private ?string $mandateId = null;
    private ?\DateTimeInterface $dueDate = null;

    private ?string $profileId = null;
    /**
     * @var array<mixed>
     */
    private array $metadata = [];

    public function __construct(private string $description,private string $redirectUrl,private Money $amount)
    {
        $this->setSequenceType(SequenceType::ONEOFF);
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

    public function getMethod(): PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(PaymentMethod $method): void
    {
        $this->method = $method;
    }

    public function getLines(): LineItemCollection
    {
        return $this->lines;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function setLocale(Locale $locale): void
    {
        $this->locale = $locale;
    }

    public function setSequenceType(SequenceType $sequenceType): void
    {
        $this->sequenceType = $sequenceType;
    }

    public function getSequenceType(): SequenceType
    {
        return $this->sequenceType;
    }

    public function setLines(LineItemCollection $lines): void
    {
        $this->lines = $lines;
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
        return $this->captureMode;
    }

    public function setCaptureMode(CaptureMode $captureMode): void
    {
        $this->captureMode = $captureMode;
    }

    public function setCardToken(string $creditCardToken): void
    {
        $this->cardToken = $creditCardToken;
    }

    public function getCardToken(): ?string
    {
        return $this->cardToken;
    }

    public function getCancelUrl(): string
    {
        return $this->cancelUrl;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $createPaymentBody = json_decode((string) json_encode($this), true);

        // Remove all entries with null values
        return array_filter($createPaymentBody, function ($entry) {
            return $entry !== null;
        });
    }

    public function setShopwareOrderNumber(string $orderNumber): void
    {
        $this->metadata['shopwareOrderNumber'] = $orderNumber;
    }

    public function getShopwareOrderNumber(): string
    {
        return $this->metadata['shopwareOrderNumber'];
    }

    public function setApplePayPaymentToken(?string $applePayPaymentToken): void
    {
        $this->applePayPaymentToken = $applePayPaymentToken;
    }

    public function getApplePayPaymentToken(): ?string
    {
        return $this->applePayPaymentToken;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function setCustomerReference(string $customerReference): void
    {
        $this->customerReference = $customerReference;
    }

    public function getCustomerReference(): ?string
    {
        return $this->customerReference;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getMandateId(): ?string
    {
        return $this->mandateId;
    }

    public function setMandateId(string $mandateId): void
    {
        $this->mandateId = $mandateId;
    }

    public function getProfileId(): ?string
    {
        return $this->profileId;
    }

    public function setProfileId(string $profileId): void
    {
        $this->profileId = $profileId;
    }
}
