<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * A single shipping option offered inside an express component.
 *
 * The reference is echoed back by Mollie once the shopper picks an option, so it has to
 * identify the Shopware shipping method.
 */
final class ShippingOption implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(
        private string $description,
        private string $reference,
        private Money $amount
    ) {
    }

    /**
     * @param array<string, mixed> $body
     */
    public static function createFromClientResponse(array $body): self
    {
        return new self(
            (string) ($body['description'] ?? ''),
            (string) ($body['reference'] ?? ''),
            Money::fromArray($body['amount'] ?? []),
        );
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return json_decode((string) json_encode($this), true);
    }
}
