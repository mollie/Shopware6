<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service\MollieApi;

use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxCollection;

class PriceCalculatorTest extends TestCase
{
    public function testConstants(): void
    {
        self::assertSame(2, PriceCalculator::MOLLIE_PRICE_PRECISION);
    }

    /**
     * test lineItem tax calcualtion
     */
    //    public function testCalculateLineItemPrice(LineItemPriceStruct $expected, CalculatedPrice $price, float $totalPrice, string $orderTaxType): void
    //    {
    //        $calculator = new PriceCalculator();
    //        $actual = $calculator->calculateLineItemPrice($price, $totalPrice, $orderTaxType);
    //        self::assertSame($expected->getVatAmount(), $actual->getVatAmount());
    //        self::assertSame($expected->getVatRate(), $actual->getVatRate());
    //        self::assertSame($expected->getTotalAmount(), $actual->getTotalAmount());
    //        self::assertSame($expected->getUnitPrice(), $actual->getUnitPrice());
    //    }

    /**
     * test that taxAmount is calculated like mollie expects it in api
     */
    //    public function testRecalculationTaxAmount(string $expected, string $orderTaxType, string $currencyCode, OrderLineItemEntity $lineItem): void
    //    {
    //        $mollieApiValues = $this->orderService->calculateLineItemPriceData($lineItem, $orderTaxType, $currencyCode);
    //        $actual = $mollieApiValues['vatAmount']['value'];
    //        $this->assertSame($expected, $actual);
    //    }

    /**
     * if a 3% off  promotion is added for a total sum of 69.90 there is a rounding
     * difference between shopware and mollie api
     */
    public function testEdgeCaseOne(): void
    {
        $mollieExpectedTaxAmount = 0.34;
        $shopwareCalculatedTaxAmount = 0.33;
        $taxRate = 19.0;
        $totalPrice = 2.1;
        $unitPrice = $totalPrice;
        $quantity = 1;
        $tax = new CalculatedTax($shopwareCalculatedTaxAmount, $taxRate, $totalPrice);
        $price = $this->getCalculatedPrice($unitPrice, $totalPrice, $this->getTaxCollection([$tax]), $quantity);

        $calculator = new PriceCalculator();
        $actualStruct = $calculator->calculateLineItemPrice($price, $totalPrice, CartPrice::TAX_STATE_GROSS);

        self::assertSame($mollieExpectedTaxAmount, $actualStruct->getVatAmount());
        self::assertSame($taxRate, $actualStruct->getVatRate());
        self::assertSame($unitPrice, $actualStruct->getUnitPrice());
        self::assertSame($totalPrice, $actualStruct->getTotalAmount());
    }

    /**
     * same as edge case one but tax configuration tax state free (configured 0% country)
     */
    public function testEdgeCaseTwo(): void
    {
        $mollieExpectedTaxAmount = 0.0;
        $shopwareCalculatedTaxAmount = 0.0;
        $taxRate = 0.3;
        $mollieExpectedTaxRate = 0.0;
        $totalPrice = 1.76;
        $unitPrice = $totalPrice;
        $quantity = 1;
        $tax = new CalculatedTax($shopwareCalculatedTaxAmount, $taxRate, $totalPrice);
        $price = $this->getCalculatedPrice($unitPrice, $totalPrice, $this->getTaxCollection([$tax]), $quantity);

        $calculator = new PriceCalculator();
        $actualStruct = $calculator->calculateLineItemPrice($price, $totalPrice, CartPrice::TAX_STATE_FREE);

        self::assertSame($mollieExpectedTaxAmount, $actualStruct->getVatAmount());
        self::assertSame($mollieExpectedTaxRate, $actualStruct->getVatRate());
        self::assertSame($unitPrice, $actualStruct->getUnitPrice());
        self::assertSame($totalPrice, $actualStruct->getTotalAmount());
    }

    /**
     * same as edge case one and two but tax configuration net
     */
    public function testEdgeCaseThree(): void
    {
        $mollieExpectedTaxAmount = 0.0;
        $shopwareCalculatedTaxAmount = 0.0;
        $taxRate = 0.0;
        $totalPrice = 1.76;
        $unitPrice = $totalPrice;
        $quantity = 1;
        $tax = new CalculatedTax($shopwareCalculatedTaxAmount, $taxRate, $totalPrice);
        $price = $this->getCalculatedPrice($unitPrice, $totalPrice, $this->getTaxCollection([$tax]), $quantity);

        $calculator = new PriceCalculator();
        $actualStruct = $calculator->calculateLineItemPrice($price, $totalPrice, CartPrice::TAX_STATE_NET);

        self::assertSame($mollieExpectedTaxAmount, $actualStruct->getVatAmount(), 'Tax amount is not same');
        self::assertSame($taxRate, $actualStruct->getVatRate(), 'Tax rate is not same');
        self::assertSame($unitPrice, $actualStruct->getUnitPrice(), 'Unit price is not same');
        self::assertSame($totalPrice, $actualStruct->getTotalAmount(), 'Total Amount is not same');
    }

    /**
     * if a 3% off  promotion is added for a total sum of 69.90 there is a rounding
     * difference between shopware and mollie api
     */
    public function testEdgeCaseFour(): void
    {
        $mollieExpectedTaxAmount = 0.34;
        $shopwareCalculatedTaxAmount = 0.33;
        $taxRate = 19.0;
        $totalPrice = 2.1;
        $unitPrice = $totalPrice;
        $quantity = 1;
        $tax = new CalculatedTax($shopwareCalculatedTaxAmount, $taxRate, $totalPrice);
        $price = $this->getCalculatedPrice($unitPrice, $totalPrice, $this->getTaxCollection([$tax]), $quantity);

        $calculator = new PriceCalculator();
        $actualStruct = $calculator->calculateLineItemPrice($price, $totalPrice, CartPrice::TAX_STATE_GROSS);

        self::assertSame($mollieExpectedTaxAmount, $actualStruct->getVatAmount());
        self::assertSame($taxRate, $actualStruct->getVatRate());
        self::assertSame($unitPrice, $actualStruct->getUnitPrice());
        self::assertSame($totalPrice, $actualStruct->getTotalAmount());
    }

    public function getVatAmountTestData(): array
    {
        return [
            //            'gross price configuration, gross price is 138,88 => (30% off discount from 462,92)' => [
            //                "9.09",
            //                CartPrice::TAX_STATE_GROSS,
            //                'EUR',
            //                $this->createOrderLineItemEntity(1, 138.88, 138.88, 9.08, 7.0)
            //            ],
            //            'taxfree configuration, net price is 129,79 => (30% off discount from 432,64)' => [
            //                "0.00",
            //                CartPrice::TAX_STATE_FREE,
            //                'EUR',
            //                $this->createOrderLineItemEntity(1, 129.79, 129.79, 0.0, 7.0, CartPrice::TAX_STATE_FREE)
            //            ],
            //            'net price configuration, net price is 129,79 => (30% off discount from 432,64)' => [
            //                "9.08",
            //                CartPrice::TAX_STATE_NET,
            //                'EUR',
            //                $this->createOrderLineItemEntity(1, 129.79, 129.79, 9.08, 7.0)
            //            ],
            //            'gross price configuration, 13.68 gross price other taxAmount' => [
            //                "1.89",
            //                CartPrice::TAX_STATE_GROSS,
            //                'EUR',
            //                $this->createOrderLineItemEntity(1, 1.1368, 13.68, 1.88, 16)
            //            ],
        ];
    }

    /**
     * @param array<int,CalculatedTax> $taxes
     *
     * @return TaxCollection
     */
    private function getTaxCollection(array $taxes): CalculatedTaxCollection
    {
        $taxCollection = new CalculatedTaxCollection([]);
        foreach ($taxes as $tax) {
            $taxCollection->add($tax);
        }

        return $taxCollection;
    }

    private function getCalculatedPrice(float $unitPrice, float $totalPrice, CalculatedTaxCollection $taxes, int $quantity): CalculatedPrice
    {
        $rules = new TaxRuleCollection([]);

        return new CalculatedPrice($unitPrice, $totalPrice, $taxes, $rules, $quantity);
    }

    private function createOrderLineItemEntity(int $quantity, float $unitPrice, float $totalPrice, float $taxAmount, float $taxRate, $orderTaxType = CartPrice::TAX_STATE_GROSS): OrderLineItemEntity
    {
        $calculatedTax = new CalculatedTax($taxAmount, $taxRate, $totalPrice);
        $tax = new CalculatedTaxCollection();
        if ($orderTaxType !== CartPrice::TAX_STATE_FREE) {
            $tax->add($calculatedTax);
        }
        $taxRule = new TaxRuleCollection();
        $price = new CalculatedPrice($unitPrice, $totalPrice, $tax, $taxRule, $quantity, null, null);

        $item = new OrderLineItemEntity();
        $item->setId(Uuid::randomHex());
        $item->setQuantity($quantity);
        $item->setPrice($price);
        $item->setTotalPrice($totalPrice);
        $item->setUnitPrice($unitPrice);

        return $item;
    }
}
