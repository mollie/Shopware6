<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

/**
 * Payload DTO for POST /v2/orders.
 *
 * Payment-specific parameters (cardToken, …) are collected in $paymentParams
 * and serialised into the `payment` sub-array, which is where the Orders API
 * expects them. The authenticationId is the exception: it must sit at the root
 * of the orders payload, not inside `payment`.
 *
 * @see https://docs.mollie.com/reference/create-order
 */
final class CreateOrder implements PaymentParameterInterface
{
    private ?Address $shippingAddress = null;
    private string $webhookUrl = '';
    private ?PaymentMethod $method = null;
    private ?string $authenticationId = null;
    /**
     * @var array<string, mixed>
     */
    private array $paymentParams = [];
    /**
     * @var array<string, mixed>
     */
    private array $metadata = [];

    public function __construct(
        private readonly string $orderNumber,
        private readonly string $redirectUrl,
        private readonly Money $amount,
        private readonly LineItemCollection $lines,
        private readonly Address $billingAddress,
        private readonly Locale $locale,
    ) {
    }

    // -------------------------------------------------------------------------
    // PaymentParameterInterface
    // -------------------------------------------------------------------------

    public function setAuthenticationId(string $id): void
    {
        $this->authenticationId = $id;
    }

    public function setCardToken(string $cardToken): void
    {
        $this->paymentParams['cardToken'] = $cardToken;
    }

    public function setApplePayPaymentToken(string $token): void
    {
        $this->paymentParams['applePayPaymentToken'] = $token;
    }

    public function setTerminalId(string $terminalId): void
    {
        // POS is excluded from the Orders API — intentional no-op.
    }

    public function setCustomerReference(string $customerReference): void
    {
        $this->paymentParams['customerReference'] = $customerReference;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->paymentParams['customerId'] = $customerId;
    }

    public function setSequenceType(SequenceType $sequenceType): void
    {
        $this->paymentParams['sequenceType'] = $sequenceType->value;
    }

    public function getMandateId(): ?string
    {
        return null;
    }

    public function getBillingAddress(): Address
    {
        return $this->billingAddress;
    }

    public function storeCredentials(): void
    {
        $this->paymentParams['storeCredentials'] = true;
    }

    // -------------------------------------------------------------------------
    // Order-level fields
    // -------------------------------------------------------------------------

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getLines(): LineItemCollection
    {
        return $this->lines;
    }

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function getShippingAddress(): ?Address
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(Address $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
    }

    public function getMethod(): ?PaymentMethod
    {
        return $this->method;
    }

    public function setMethod(PaymentMethod $method): void
    {
        $this->method = $method;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'amount' => $this->amount->toArray(),
            'orderNumber' => $this->orderNumber,
            'lines' => $this->buildLinesArray(),
            'billingAddress' => json_decode((string) json_encode($this->billingAddress), true),
            'locale' => $this->locale->value,
            'redirectUrl' => $this->redirectUrl,
        ];

        if ($this->shippingAddress !== null) {
            $data['shippingAddress'] = json_decode((string) json_encode($this->shippingAddress), true);
        }
        if (mb_strlen($this->webhookUrl) > 0) {
            $data['webhookUrl'] = $this->webhookUrl;
        }
        if ($this->method !== null) {
            $data['method'] = $this->method->value;
        }
        if (count($this->metadata) > 0) {
            $data['metadata'] = $this->metadata;
        }
        if ($this->authenticationId !== null) {
            $data['authenticationId'] = $this->authenticationId;
        }
        $paymentParams = array_filter($this->paymentParams, function ($value) {
            return $value !== null;
        });
        if (count($paymentParams) > 0) {
            $data['payment'] = $paymentParams;
        }

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLinesArray(): array
    {
        $lines = [];
        foreach ($this->lines as $line) {
            $lineData = [
                'type' => $line->getType()->value,
                'name' => $line->getDescription(),
                'quantity' => $line->getQuantity(),
                'unitPrice' => $line->getUnitPrice()->toArray(),
                'totalAmount' => $line->getAmount()->toArray(),
            ];

            try {
                $lineData['vatRate'] = $line->getVatRate();
                $lineData['vatAmount'] = $line->getVatAmount()->toArray();
            } catch (\Error) {
                // vatRate/vatAmount not set for zero-tax items
            }

            $sku = $line->getSku();
            if ($sku !== '') {
                $lineData['sku'] = $sku;
            }

            $metadata = $line->getMetadata();
            if (count($metadata) > 0) {
                $lineData['metadata'] = $metadata;
            }

            $lines[] = $lineData;
        }

        return $lines;
    }
}
