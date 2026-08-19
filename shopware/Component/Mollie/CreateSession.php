<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * Payload for POST /v2/sessions.
 *
 * @see https://docs.mollie.com/reference/create-session
 */
final class CreateSession implements \JsonSerializable
{
    use JsonSerializableTrait;

    private string $cancelUrl = '';
    private LineItemCollection $lines;
    private SequenceType $sequenceType;
    private ?Address $billingAddress = null;
    private ?Address $shippingAddress = null;
    private ?string $customerId = null;
    private ?string $profileId = null;
    private ?string $webhookUrl = null;

    /**
     * @var array<mixed>
     */
    private array $metadata = [];

    /**
     * @var string[] one or more of email, billing-address, shipping-address
     */
    private array $requiredCustomerDetails = [];

    public function __construct(private string $description, private string $redirectUrl, private Money $amount)
    {
        $this->lines = new LineItemCollection();
        $this->sequenceType = SequenceType::ONEOFF;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getCancelUrl(): string
    {
        return $this->cancelUrl;
    }

    public function setCancelUrl(string $cancelUrl): void
    {
        $this->cancelUrl = $cancelUrl;
    }

    public function getLines(): LineItemCollection
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

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getProfileId(): ?string
    {
        return $this->profileId;
    }

    public function setProfileId(string $profileId): void
    {
        $this->profileId = $profileId;
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    /**
     * @return string[]
     */
    public function getRequiredCustomerDetails(): array
    {
        return $this->requiredCustomerDetails;
    }

    /**
     * @param string[] $requiredCustomerDetails
     */
    public function setRequiredCustomerDetails(array $requiredCustomerDetails): void
    {
        $this->requiredCustomerDetails = $requiredCustomerDetails;
    }

    /**
     * @return array<mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<mixed> $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $body = json_decode((string) json_encode($this), true);

        // the webhook of a session lives inside the payment sub object, not at root level
        unset($body['webhookUrl']);
        if ($this->webhookUrl !== null) {
            $body['payment'] = ['webhookUrl' => $this->webhookUrl];
        }

        return array_filter($body, function ($entry) {
            if (is_array($entry)) {
                return count($entry) > 0;
            }

            return $entry !== null;
        });
    }
}
