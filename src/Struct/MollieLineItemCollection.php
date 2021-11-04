<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Struct;

use Mollie\Api\Types\OrderLineType;
use Shopware\Core\Framework\Struct\StructCollection;

/**
 * @method void                add(MollieLineItem $entity)
 * @method void                set(string $key, MollieLineItem $entity)
 * @method MollieLineItem[]    getIterator()
 * @method MollieLineItem[]    getElements()
 * @method MollieLineItem|null get(string $key)
 * @method MollieLineItem|null first()
 * @method MollieLineItem|null last()
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
}
