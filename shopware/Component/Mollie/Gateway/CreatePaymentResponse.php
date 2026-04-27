<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Psr\Http\Message\ResponseInterface;

final class CreatePaymentResponse
{
    public function __construct(private string $paymentId, private PaymentStatus $paymentStatus, private ?string $checkoutUrl)
    {
    }

    public static function fromClientResponse(ResponseInterface $response): self
    {
        $body = json_decode($response->getBody()->getContents(), true);

        return new self(
            $body['id'],
            new PaymentStatus($body['status']),
            $body['_links']['checkout']['href'] ?? null
        );
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function getCheckoutUrl(): ?string
    {
        return $this->checkoutUrl;
    }
}
