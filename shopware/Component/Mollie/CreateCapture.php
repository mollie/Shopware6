<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class CreateCapture implements \JsonSerializable
{
    use JsonSerializableTrait;

    private Money $amount;
    private string $description;

    public function __construct(ShippingItemCollection $items, string $currencyIsoCode)
    {
        $this->amount = new Money($items->getTotalAmount(), $currencyIsoCode);
        $this->description = $items->getDescription();
    }

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function setAmount(Money $amount): void
    {
        $this->amount = $amount;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(): array
    {
        return json_decode((string) json_encode($this), true);
    }
}
