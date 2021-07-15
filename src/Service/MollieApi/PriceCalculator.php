<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;

class PriceCalculator
{
    public const MOLLIE_PRICE_PRECISION = 2;

    public function calculateLineItemPrice(CalculatedPrice $price, float $lineItemTotalPrice, string $orderTaxType): LineItemPriceStruct
    {
        $taxCollection = $price->getCalculatedTaxes();

        $vatRate = 0.0;
        $itemTax = $this->getHighestTax($taxCollection);

        if ($itemTax instanceof CalculatedTax) {
            $vatRate = $itemTax->getTaxRate();
        }

        // Remove VAT if the order is tax free
        if ($orderTaxType === CartPrice::TAX_STATE_FREE) {
            $vatRate = 0.0;
        }

        $unitPrice = $price->getUnitPrice();

        // If the order is of type TAX_STATE_NET the $lineItemTotalPrice and unit price
        // is a net price.
        // For correct mollie api tax calculations we have to calculate the shopware gross
        // price
        if ($orderTaxType === CartPrice::TAX_STATE_NET) {
            $unitPrice *= ((100 + $vatRate) / 100);
            $lineItemTotalPrice += $taxCollection->getAmount();
        }

        $unitPrice = round($unitPrice, self::MOLLIE_PRICE_PRECISION);

        $roundedLineItemTotalPrice = round($lineItemTotalPrice, self::MOLLIE_PRICE_PRECISION);
        $roundedVatRate = round($vatRate, self::MOLLIE_PRICE_PRECISION);
        $vatAmount = $roundedLineItemTotalPrice * ($roundedVatRate / (100 + $roundedVatRate));
        $roundedVatAmount = round($vatAmount, self::MOLLIE_PRICE_PRECISION);

        return new LineItemPriceStruct($unitPrice, $roundedLineItemTotalPrice, $roundedVatAmount, $roundedVatRate);
    }

    /**
     * Return a calculated tax struct for a line item. The tax rate is recalculated from multiple taxRates to
     * one taxRate that will fit for the lineItem
     *
     * @param CalculatedTaxCollection $taxCollection
     * @return CalculatedTax|null
     */
    public function getHighestTax(CalculatedTaxCollection $taxCollection): ?CalculatedTax
    {
        if ($taxCollection->count() === 0) {
            return null;
        }

        $taxCollection->sort(static function (CalculatedTax $taxOne, CalculatedTax $taxTwo) {
            if ($taxOne->getTaxRate() === $taxTwo->getTaxRate()) {
                return 0;
            }

            return ($taxOne->getTaxRate() < $taxTwo->getTaxRate() ? 1 : -1);
        });

        return $taxCollection->first();
    }

}
