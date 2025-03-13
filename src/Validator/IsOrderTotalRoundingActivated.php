<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Validator;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;

class IsOrderTotalRoundingActivated
{
    /**
     * function validates to true if we have to consider new 6.4 rounding feature
     */
    public function isNewRoundingActive(int $decimals, bool $roundForNet, float $interval, string $orderTaxState): bool
    {
        if (! $this->considerRounding($decimals, $roundForNet, $orderTaxState)) {
            return false;
        }

        // if we have no normal rounding behaviour we validate true
        return $interval !== 0.01;
    }

    /**
     * function tells us if we could consider that rounding configuration would lead to new
     * rounding behaviour
     */
    private function considerRounding(int $decimals, bool $roundForNet, string $taxState): bool
    {
        if ($decimals !== 2) {
            return false;
        }

        if ($taxState === CartPrice::TAX_STATE_GROSS) {
            return true;
        }

        return $roundForNet;
    }
}
