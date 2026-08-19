<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<ShippingOption>
 */
final class ShippingOptionCollection extends Collection
{
    /**
     * @param array<mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $collection = new self();
        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            $collection->add(ShippingOption::createFromClientResponse($value));
        }

        return $collection;
    }

    public function getByReference(string $reference): ?ShippingOption
    {
        foreach ($this->getElements() as $shippingOption) {
            if ($shippingOption->getReference() === $reference) {
                return $shippingOption;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toArray(): array
    {
        $values = [];
        foreach ($this->getElements() as $shippingOption) {
            $values[] = $shippingOption->toArray();
        }

        return $values;
    }

    protected function getExpectedClass(): ?string
    {
        return ShippingOption::class;
    }
}
