<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

/**
 * Payload for Mollie's "Update payment" endpoint (PATCH /v2/payments/{id}).
 *
 * Only the fields the endpoint actually accepts are modelled here; the values are copied over
 * from a {@see CreatePayment} at the call site. Immutable data like amount, order lines and the
 * sequence type are intentionally absent.
 *
 * @see https://docs.mollie.com/reference/update-payment
 */
final class UpdatePayment
{
    private ?string $cancelUrl = null;
    private ?string $webhookUrl = null;
    private ?PaymentMethod $method = null;
    private ?Locale $locale = null;
    private ?Address $billingAddress = null;
    private ?Address $shippingAddress = null;
    private ?\DateTimeInterface $dueDate = null;

    /**
     * @var array<mixed>
     */
    private array $metadata = [];

    public function __construct(private string $description, private string $redirectUrl)
    {
    }

    public function setCancelUrl(string $cancelUrl): void
    {
        $this->cancelUrl = $cancelUrl;
    }

    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    public function setMethod(PaymentMethod $method): void
    {
        $this->method = $method;
    }

    public function setLocale(Locale $locale): void
    {
        $this->locale = $locale;
    }

    public function setBillingAddress(Address $billingAddress): void
    {
        $this->billingAddress = $billingAddress;
    }

    public function setShippingAddress(Address $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    public function setDueDate(\DateTimeInterface $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    public function setShopwareOrderNumber(string $orderNumber): void
    {
        $this->metadata['shopwareOrderNumber'] = $orderNumber;
    }

    public function getShopwareOrderNumber(): string
    {
        return $this->metadata['shopwareOrderNumber'] ?? '';
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $body = [
            'description' => $this->description,
            'redirectUrl' => $this->redirectUrl,
            'cancelUrl' => $this->cancelUrl,
            'webhookUrl' => $this->webhookUrl,
            'method' => $this->method?->value,
            'locale' => $this->locale?->value,
            'billingAddress' => $this->billingAddress?->jsonSerialize(),
            'shippingAddress' => $this->shippingAddress?->jsonSerialize(),
            'metadata' => $this->metadata,
            'dueDate' => $this->dueDate?->format('Y-m-d'),
        ];

        return array_filter($body, function ($entry) {
            return $entry !== null;
        });
    }
}
