<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressMethod;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<VisibilityRestriction>
 */
final class VisibilityRestrictionCollection extends Collection
{
    /**
     * @param array<null|string> $values
     */
    public static function fromArray(array $values): self
    {
        $collection = new self();
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $restriction = VisibilityRestriction::tryFrom($value);
            if ($restriction === null) {
                continue;
            }
            $collection->add($restriction);
        }

        return $collection;
    }

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        $values = [];
        foreach ($this->elements as $restriction) {
            $values[] = $restriction->value;
        }

        return $values;
    }
}
