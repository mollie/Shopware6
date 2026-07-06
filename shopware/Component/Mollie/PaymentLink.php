<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

/**
 * Response struct for a Mollie payment link.
 *
 * @see https://docs.mollie.com/reference/get-payment-link
 */
final class PaymentLink
{
    private string $paymentLinkUrl = '';

    public function __construct(private readonly string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getPaymentLinkUrl(): string
    {
        return $this->paymentLinkUrl;
    }

    public function setPaymentLinkUrl(string $paymentLinkUrl): void
    {
        $this->paymentLinkUrl = $paymentLinkUrl;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        $paymentLink = new self((string) ($body['id'] ?? ''));

        $url = $body['_links']['paymentLink']['href'] ?? null;
        if ($url !== null) {
            $paymentLink->setPaymentLinkUrl((string) $url);
        }

        return $paymentLink;
    }
}
