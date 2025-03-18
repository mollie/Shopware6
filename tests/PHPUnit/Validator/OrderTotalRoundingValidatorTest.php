<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Validator;

use Kiener\MolliePayments\Validator\IsOrderTotalRoundingActivated;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;

class OrderTotalRoundingValidatorTest extends TestCase
{
    /**
     * wrong decimal configuration => validator should return false
     */
    public function testValidateWrongDecimalRounding(): void
    {
        $decimals = 3;
        $interval = 0.5;
        $roundForNet = true;
        $orderTaxState = CartPrice::TAX_STATE_NET;

        $this->assertFalse((new IsOrderTotalRoundingActivated())->isNewRoundingActive($decimals, $roundForNet, $interval, $orderTaxState));
    }

    /**
     * wrong interval configuration => validator should return false
     *
     * if the interval === 0.01 then rounding could not be responsible for price differences
     */
    public function testValidateWrongIntervalRounding(): void
    {
        $decimals = 2;
        $interval = 0.01;
        $roundForNet = true;
        $orderTaxState = CartPrice::TAX_STATE_NET;

        $this->assertFalse((new IsOrderTotalRoundingActivated())->isNewRoundingActive($decimals, $roundForNet, $interval, $orderTaxState));
    }

    /**
     * wrong tax state config => validator should return false
     *
     * if the tax state is configured that no net rounding should be available and we have a NET order,
     * then rounding could not be responsible for price differences
     */
    public function testValidateWrongTaxState(): void
    {
        $decimals = 2;
        $interval = 0.5;
        $roundForNet = false;
        $orderTaxState = CartPrice::TAX_STATE_NET;

        $this->assertFalse((new IsOrderTotalRoundingActivated())->isNewRoundingActive($decimals, $roundForNet, $interval, $orderTaxState));
    }

    /**
     * wrong tax state config => validator should return true
     *
     * if the tax state is configured that net rounding should be available and we have a NET order,
     * then rounding could be responsible for price differences
     */
    public function testValidateCorrectTaxStateNetRounding(): void
    {
        $decimals = 2;
        $interval = 0.5;
        $roundForNet = true;
        $orderTaxState = CartPrice::TAX_STATE_NET;

        $this->assertTrue((new IsOrderTotalRoundingActivated())->isNewRoundingActive($decimals, $roundForNet, $interval, $orderTaxState));
    }

    /**
     * net price shouldn't be rounded, it's a gross order, ignore net setting => validator returns true
     *
     * if the tax state is configured that no net rounding should be available and we have a GROSS order,
     * then rounding could be responsible for price differences
     */
    public function testValidateCorrectTaxStateButOrderIsGross(): void
    {
        $decimals = 2;
        $interval = 0.5;
        $roundForNet = false;
        $orderTaxState = CartPrice::TAX_STATE_GROSS;

        $this->assertTrue((new IsOrderTotalRoundingActivated())->isNewRoundingActive($decimals, $roundForNet, $interval, $orderTaxState));
    }
}
