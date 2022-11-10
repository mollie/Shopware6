<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Framework\Struct\StructCollection;

/**
 * @extends StructCollection<MollieLineItem>
 * @method void                add(MollieLineItem $entity)
 * @method void                set(string $key, MollieLineItem $entity)
 * @method MollieLineItem[]    getIterator()
 * @method MollieLineItem[]    getElements()
 * @method null|MollieLineItem get(string $key)
 * @method null|MollieLineItem first()
 * @method null|MollieLineItem last()
 */
class MollieLineItemCollection extends StructCollection
{
    protected function getExpectedClass(): string
    {
        return MollieLineItem::class;
    }

    public function filterByRoundingRest(): self
    {
        return $this->filter(function (MollieLineItem $lineItem) {
            return $lineItem->hasRoundingRest();
        });
    }

    public function filterByProductType(): self
    {
        return $this->filter(function (MollieLineItem $lineItem) {
            return $lineItem->getType() === OrderLineType::TYPE_PHYSICAL;
        });
    }

    public function getRoundingRestSum(): float
    {
        $filteredItems = $this->filterByRoundingRest();
        $roundingSum = 0.0;

        /** @var MollieLineItem $item */
        foreach ($filteredItems as $item) {
            $roundingSum += $item->getPrice()->getRoundingRest();
        }

        return $roundingSum;
    }

    /**
     * @return float
     */
    public function getCartTotalAmount(): float
    {
        $sum = 0;

        foreach ($this->getElements() as $item) {
            $sum += $item->getPrice()->getTotalAmount();
        }

        return $sum;
    }
}
