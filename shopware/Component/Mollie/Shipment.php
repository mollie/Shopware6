<?php

declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class Shipment
{
    public function __construct(private string $id)
    {
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        return new self((string) ($body['id'] ?? ''));
    }

    public function getId(): string
    {
        return $this->id;
    }
}
