<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class Order
{
    public function __construct(
        private readonly string $id,
        private readonly string $checkoutUrl,
        private readonly string $paymentId
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCheckoutUrl(): string
    {
        return $this->checkoutUrl;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        $checkoutUrl = $body['_links']['checkout']['href'] ?? '';
        $paymentId = $body['_embedded']['payments'][0]['id'] ?? null;

        if ($paymentId === null) {
            throw new \RuntimeException(sprintf('Mollie order "%s" has no embedded payment in API response', $body['id'] ?? 'unknown'));
        }

        return new self($body['id'] ?? '', $checkoutUrl, $paymentId);
    }
}
