<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

abstract class CreateRefund
{
    protected string $description = '';

    /**
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
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
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getLines(): LineItemCollection
    {
        return new LineItemCollection();
    }

    public function getAmount(): ?Money
    {
        return null;
    }
}
