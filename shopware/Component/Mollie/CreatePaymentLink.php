<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

/**
 * Payload DTO for POST /v2/payment-links.
 *
 * Built from a {@see CreatePayment} (see PayloadBuilder::buildPaymentLink), analogous to how
 * {@see CreateOrder} is built from a payment. The only conceptual difference to a regular payment
 * is that the payment method is not a single one but a list of allowed methods, and a link is not
 * reusable. `testmode` is not part of the payload - the API key already selects test vs. live.
 *
 * @see https://docs.mollie.com/reference/create-payment-link
 */
final class CreatePaymentLink
{
    private string $webhookUrl = '';
    private bool $reusable = false;
    private ?string $customerId = null;
    /**
     * @var string[]
     */
    private array $allowedMethods = [];

    public function __construct(
        private readonly string $description,
        private readonly string $redirectUrl,
        private readonly Money $amount,
        private readonly LineItemCollection $lines,
        private readonly Address $billingAddress,
        private readonly Address $shippingAddress,
        private readonly SequenceType $sequenceType,
    ) {
    }

    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
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
            'redirectUrl' => $this->redirectUrl,
            'reusable' => $this->reusable,
            'lines' => json_decode((string) json_encode($this->lines), true),
            'billingAddress' => json_decode((string) json_encode($this->billingAddress), true),
            'shippingAddress' => json_decode((string) json_encode($this->shippingAddress), true),
            'sequenceType' => $this->sequenceType->value,
        ];

        if ($this->webhookUrl !== '') {
            $data['webhookUrl'] = $this->webhookUrl;
        }
        if (count($this->allowedMethods) > 0) {
            $data['allowedMethods'] = $this->allowedMethods;
        }
        if ($this->customerId !== null) {
            $data['customerId'] = $this->customerId;
        }

        return $data;
    }
}
