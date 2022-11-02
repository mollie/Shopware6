<?php

namespace Kiener\MolliePayments\Service\MollieApi\Fixer;

use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;

class OrderAmountDiffFixer
{

    /**
     * the maximum allowed difference to fix the order.
     * if max is exceeded, it will NOT get fixed.
     */
    private const MAX_DIFF_CENTS = 0.01;


    /**
     * @param float $orderTotal
     * @param MollieLineItemCollection $lineItems
     * @return MollieLineItemCollection
     */
    public function fixSmallAmountDiff(float $orderTotal, MollieLineItemCollection $lineItems): MollieLineItemCollection
    {
        $sumLines = 0;

        foreach ($lineItems as $lineItem) {
            $sumLines += $lineItem->getPrice()->getTotalAmount();
        }

        $diff = $orderTotal - $sumLines;

        $diffAbs = abs($diff);

        if ($diffAbs > 0.0 && $diffAbs <= self::MAX_DIFF_CENTS) {
            $firstItem = $lineItems->getElements()[0];

            $fixedTotalPrice = $firstItem->getPrice()->getTotalAmount() + $diff;

            $existingPrice = $firstItem->getPrice();

            $price = new LineItemPriceStruct(
                $existingPrice->getUnitPrice(),
                $fixedTotalPrice,
                $existingPrice->getVatAmount(),
                $existingPrice->getVatRate(),
                $existingPrice->getRoundingRest()
            );

            $firstItem->setPrice($price);
        }

        return $lineItems;
    }
}
