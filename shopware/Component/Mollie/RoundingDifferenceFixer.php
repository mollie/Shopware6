<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class RoundingDifferenceFixer implements RoundingDifferenceFixerInterface
{
    public const DEFAULT_TITLE = 'Automatic Rounding Difference';
    public const METADATA_TYPE = 'rounding';

    public function fixAmountDiff(Money $orderTotal, LineItemCollection $lineItems, string $title, string $sku): LineItemCollection
    {
        // The diff has to be calculated on the values Mollie actually receives,
        // therefore everything is rounded to the currency precision of the amount.
        $decimals = $orderTotal->getDecimals();

        $sumLines = 0.0;
        foreach ($lineItems as $lineItem) {
            $sumLines += round($lineItem->getAmount()->getValue(), $decimals);
        }

        $diff = round(round($orderTotal->getValue(), $decimals) - $sumLines, $decimals);

        if (abs($diff) <= 0.0) {
            return $lineItems;
        }

        $name = $title !== '' ? $title : self::DEFAULT_TITLE;
        $price = new Money($diff, $orderTotal->getCurrency());
        $vatAmount = new Money(0.0, $orderTotal->getCurrency());

        $type = LineItemType::DIGITAL;
        if ($diff < 0.0) {
            $type = LineItemType::CREDIT;
        }

        $lineItem = new LineItem($name, 1, $price, $price);
        $lineItem->setType($type);
        $lineItem->setVatRate('0');
        $lineItem->setVatAmount($vatAmount);

        if ($sku !== '') {
            $lineItem->setSku($sku);
        }

        // kept for internal (technical) identification later on (e.g. in refund manager)
        $lineItem->setMetadata(['type' => self::METADATA_TYPE]);

        $lineItems->add($lineItem);

        return $lineItems;
    }
}
