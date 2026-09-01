<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Response of POST /v2/payment-links. The created link id is persisted on the order
 * transaction as part of the regular Mollie payment extension ({@see Payment::getPaymentLinkId()}).
 *
 * @see https://docs.mollie.com/reference/create-payment-link
 */
final class PaymentLink extends Struct
{
    public function __construct(
        private string $id,
        private string $url = '',
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): void
    {
        $this->url = $url;
    }

    /**
     * @param array<mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        return new self(
            (string) ($body['id'] ?? ''),
            (string) ($body['_links']['paymentLink']['href'] ?? ''),
        );
    }

    /**
     * @return array{id: string, url: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
        ];
    }
}
