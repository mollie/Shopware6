<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

if (class_exists(TaxCalculator::class)) {
    return;
}

class TaxCalculator
{
    public function calculateGrossTaxes(float $price, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        $taxes = new CalculatedTaxCollection();
        foreach ($rules as $rule) {
            $taxes->add($this->calculateTaxFromGrossPrice($price, $rule));
        }

        return $taxes;
    }

    public function calculateNetTaxes(float $price, TaxRuleCollection $rules): CalculatedTaxCollection
    {
        $taxes = new CalculatedTaxCollection();
        foreach ($rules as $rule) {
            $taxes->add($this->calculateTaxFromNetPrice($price, $rule));
        }

        return $taxes;
    }

    private function calculateTaxFromGrossPrice(float $gross, TaxRule $rule): CalculatedTax
    {
        $priceShareOfRule = $gross / 100 * $rule->getPercentage();
        $taxRate = $rule->getTaxRate();
        $taxAmount = $priceShareOfRule * $taxRate / (100 + $taxRate);

        return new CalculatedTax(round($taxAmount, 2), $taxRate, round($priceShareOfRule, 2));
    }

    private function calculateTaxFromNetPrice(float $net, TaxRule $rule): CalculatedTax
    {
        $priceShareOfRule = $net / 100 * $rule->getPercentage();
        $taxRate = $rule->getTaxRate();
        $taxAmount = $priceShareOfRule * $taxRate / 100;

        return new CalculatedTax(round($taxAmount, 2), $taxRate, round($priceShareOfRule, 2));
    }
}
