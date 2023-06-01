<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\MollieApi;

use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;

class PriceCalculator
{
    /**
     *
     */
    public const MOLLIE_PRICE_PRECISION = 2;


    /**
     * @param CalculatedPrice $price
     * @param float $lineItemTotalPrice
     * @param string $orderTaxType
     * @param bool $isVerticalTaxCalculation
     * @return LineItemPriceStruct
     */
    public function calculateLineItemPrice(CalculatedPrice $price, float $lineItemTotalPrice, string $orderTaxType, bool $isVerticalTaxCalculation = false): LineItemPriceStruct
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

        # this can be the NET or GROSS price
        # depending on the customer group setting
        $unitPriceOriginal = $price->getUnitPrice();

        # we need a gross price for Mollie
        # let's first assume this is a gross price
        $unitPriceGross = $price->getUnitPrice();


        // If the order is of type TAX_STATE_NET the $lineItemTotalPrice and unit price is a net price.
        // For correct mollie api tax calculations we have to calculate the shopware gross price
        if ($orderTaxType === CartPrice::TAX_STATE_NET) {
            $unitPriceGross *= ((100 + $vatRate) / 100);

            if ($isVerticalTaxCalculation) {
                /**
                 * if vertical tax calculation is configured, taxes aren't calculated on each lineitem but on the sum of
                 * all lineItems. If net prices are active we only have net prices for a shopware lineItem. Mollie does
                 * always horizontal tax calculation => we need gross prices on lineItems and have to calculate them here.
                 *
                 * Because of this we store the rests after 2 decimals for each lineItem. This is the rounding rest (we
                 * always round down) Afterwards we may take the rest sum and round it to correct missing sum
                 * Then we add this missing sum to one lineItem and get the correct horizontal tax calculation pout of the
                 * vertical tax calculation
                 */
                $correctGrossPrice = $price->getTotalPrice() * ((100 + $vatRate) / 100);
                $roundedTaxAmount = round($price->getTotalPrice() * ($vatRate / 100), self::MOLLIE_PRICE_PRECISION);
                $lineItemTotalPrice = round($correctGrossPrice, self::MOLLIE_PRICE_PRECISION);
                $roundingRest = $correctGrossPrice - $lineItemTotalPrice;
                $roundedVatRate = round($vatRate, self::MOLLIE_PRICE_PRECISION);

                return new LineItemPriceStruct($unitPriceGross, $lineItemTotalPrice, $roundedTaxAmount, $roundedVatRate, $roundingRest);
            }

            $lineItemTotalPrice += $taxCollection->getAmount();
        }

        $unitPriceGross = round($unitPriceGross, self::MOLLIE_PRICE_PRECISION);

        $roundedLineItemTotalPrice = round($lineItemTotalPrice, self::MOLLIE_PRICE_PRECISION);
        $roundedVatRate = round($vatRate, self::MOLLIE_PRICE_PRECISION);
        $vatAmount = $roundedLineItemTotalPrice * ($roundedVatRate / (100 + $roundedVatRate));
        $roundedVatAmount = round($vatAmount, self::MOLLIE_PRICE_PRECISION);


        # if we have multiple tax rates and amounts (very likely a promotion)
        # then we have to combine those amounts and fake a new calculated tax rate.
        # if we would just use e.g. the highest tax rate for calculation, then it could happen
        # then the total amounts of vat rates is a negative one (promotion is negative). so we always have
        # to use the correct pre-calculated tax amounts and build a single sum.
        # and that obviously leads to a non-existing tax rate that we have to calculate in here
        # steps to reproduce: make sure to have a multi-tax promotion where the higher tax rate would lead to a bigger vat amount for the whole cart.
        #                     this means that the actual calculation would need more items with the lower tax rate.
        # sample cart:
        #       item #1: 10% tax rate, net price: 11.34, quantity 3
        #       item #2: 20% tax rate, net price: 13.03, quantity 1
        #       promotion: 50 EUR amount off on full cart
        if ($taxCollection->count() > 1) {
            # start by summing up the individual
            # tax amounts from the tax rates
            $vatAmount = 0;
            foreach ($taxCollection->getElements() as $tax) {
                $vatAmount += $tax->getTax();
            }
            $roundedVatAmount = round($vatAmount, self::MOLLIE_PRICE_PRECISION);

            # now calculate our fake tax rate
            # from the final price and vat amount value
            $net = $roundedLineItemTotalPrice - $vatAmount;

            if ((float)$net !== 0.0) {
                $fakeTaxRate = $vatAmount / $net * 100;
            } else {
                # this happened once, division by zero
                # so just make 0 out of it (worked for client)
                $fakeTaxRate = 0;
            }

            $roundedVatRate = round($fakeTaxRate, 2);

            # if we have a net price, then the calculated gross price is wrong.
            # we now have a new mixed vat rate, which doesn't match our already calculated gross price.
            if ($orderTaxType === CartPrice::TAX_STATE_NET) {
                # the total amount is already correct,
                # so we just divide it by the quantity, and that should work.
                $unitPriceGross = round($roundedLineItemTotalPrice / $price->getQuantity(), 2);
            }
        }


        return new LineItemPriceStruct($unitPriceGross, $roundedLineItemTotalPrice, $roundedVatAmount, $roundedVatRate);
    }

    /**
     * Return a calculated tax struct for a line item. The tax rate is recalculated from multiple taxRates to
     * one taxRate that will fit for the lineItem
     *
     * @param CalculatedTaxCollection $taxCollection
     * @return null|CalculatedTax
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
