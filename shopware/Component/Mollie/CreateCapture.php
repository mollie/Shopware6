<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;

final class CreateCapture implements \JsonSerializable
{
    use JsonSerializableTrait;

    public function __construct(private Money $money, private string $description)
    {
    }

    public function getMoney(): Money
    {
        return $this->money;
    }

    public function setMoney(Money $money): void
    {
        $this->money = $money;
    }

    public function add(Money $money): void
    {
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
        return $captureArray = [
            'amount' => $this->money->toArray(),
            'description' => $this->description,
        ];
    }
}
