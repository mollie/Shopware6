<?php

namespace Kiener\MolliePayments\Service\MollieApi\Fixer;

use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;
use Mollie\Api\Types\OrderLineType;

class RoundingDifferenceFixer
{
    private const DEFAULT_TITLE = 'Automatic Rounding Difference';
    private const DEFAULT_SKU = '';


    /**
     * @param float $orderTotal
     * @param MollieLineItemCollection $lineItems
     * @param string $title
     * @param string $sku
     * @return MollieLineItemCollection
     */
    public function fixAmountDiff(float $orderTotal, MollieLineItemCollection $lineItems, string $title, string $sku): MollieLineItemCollection
    {
        $sumLines = 0;

        foreach ($lineItems as $lineItem) {
            $sumLines += $lineItem->getPrice()->getTotalAmount();
        }

        # our good old diff problem with floating points :)
        # to avoid that 0.01 is 0.009999999998,
        # we multiply with a high number and divide again
        $diff = ($orderTotal * 10000) - ($sumLines * 10000);
        $diff = $diff / 10000;

        $diffAbs = abs($diff);

        if ($diffAbs > 0) {
            $price = new LineItemPriceStruct(
                $diff,
                $diff,
                0,
                0,
                0
            );

            $name = (!empty($title)) ? $title : self::DEFAULT_TITLE;
            $sku = (!empty($sku)) ? $sku : self::DEFAULT_SKU;

            $mollieLineItem = new MollieLineItem(
                OrderLineType::TYPE_PHYSICAL,
                $name,
                1,
                $price,
                '',
                $sku,
                '',
                ''
            );

            # we need this for further (technical) identification later on (e.g. in refund manager)
            $mollieLineItem->addMetaData('type', 'rounding');

            $lineItems->add($mollieLineItem);
        }

        return $lineItems;
    }
}
