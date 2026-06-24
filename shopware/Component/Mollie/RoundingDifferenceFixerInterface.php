<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

interface RoundingDifferenceFixerInterface
{
    /**
     * Adds an additional line item that compensates the difference between the
     * order total and the sum of all line items (rounded to the currency precision
     * Mollie accepts). This keeps the Mollie payload consistent when Shopware uses
     * more decimals than Mollie allows.
     */
    public function fixAmountDiff(Money $orderTotal, LineItemCollection $lineItems, string $title, string $sku): LineItemCollection;
}
