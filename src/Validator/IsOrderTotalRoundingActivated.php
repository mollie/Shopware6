<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Validator;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;

class IsOrderTotalRoundingActivated
{
    /**
     * function validates to true if we have to consider new 6.4 rounding feature
     *
     * @param int $decimals
     * @param bool $roundForNet
     * @param float $interval
     * @param string $orderTaxState
     * @return bool
     */
    public function isNewRoundingActive(int $decimals, bool $roundForNet, float $interval, string $orderTaxState): bool
    {
        if (!$this->considerRounding($decimals, $roundForNet, $orderTaxState)) {
            return false;
        }

        // if we have no normal rounding behaviour we validate true
        return $interval !== 0.01;
    }

    /**
     * function tells us if we could consider that rounding configuration would lead to new
     * rounding behaviour
     *
     * @param int $decimals
     * @param bool $roundForNet
     * @param string $taxState
     * @return bool
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
