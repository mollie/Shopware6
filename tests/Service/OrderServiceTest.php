<?php declare(strict_types=1);


namespace Kiener\MolliePayments\Tests\Service;


use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Validator\OrderLineItemValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;


class OrderServiceTest extends TestCase
{

    /**
     * @var OrderService
     */
    private $orderService;

    public function setUp(): void
    {
        $orderRepository = $this->getMockBuilder(EntityRepositoryInterface::class)->disableOriginalConstructor()->getMock();
        $lineItemRepository = $this->getMockBuilder(EntityRepositoryInterface::class)->disableOriginalConstructor()->getMock();
        $logger = new NullLogger();
        $this->orderService = new OrderService(
            $orderRepository,
            $lineItemRepository,
            $logger,
            new OrderLineItemValidator($logger)
        );

    }

    /**
     * test that taxAmount is calculated like mollie expects it in api
     *
     * @param float $expected
     * @param string $orderTaxType
     * @param string $currencyCode
     * @param OrderLineItemEntity $lineItem
     * @dataProvider getVatAmountTestData
     */
    public function testRecalculationTaxAmount(string $expected, string $orderTaxType, string $currencyCode, OrderLineItemEntity $lineItem): void
    {
        $mollieApiValues = $this->orderService->calculateLineItemPriceData($lineItem, $orderTaxType, $currencyCode);
        $actual = $mollieApiValues['vatAmount']['value'];
        $this->assertSame($expected, $actual);
    }


    public function getVatAmountTestData(): array
    {
        return [
            'gross price configuration, 2,10 => (3% off discount from 69,90)' => [
                "0.34",
                CartPrice::TAX_STATE_GROSS,
                'EUR',
                $this->createOrderLineItemEntity(1, 2.10, 2.10, 0.33, 19.0)
            ],
            'taxfree configuration, net price is 1,76 => (3% off discount from 69,90)' => [
                "0.00",
                CartPrice::TAX_STATE_FREE,
                'EUR',
                $this->createOrderLineItemEntity(1, 1.76, 1.76, 0.0, 19.0, CartPrice::TAX_STATE_FREE)
            ],
            'net price configuration, 1,76 => (3% off discount from 58,74)' => [
                "0.33",
                CartPrice::TAX_STATE_NET,
                'EUR',
                $this->createOrderLineItemEntity(1, 1.76, 1.76, 0.33, 19.0)
            ],
            'gross price configuration, gross price is 138,88 => (30% off discount from 462,92)' => [
                "9.09",
                CartPrice::TAX_STATE_GROSS,
                'EUR',
                $this->createOrderLineItemEntity(1, 138.88, 138.88, 9.08, 7.0)
            ],
            'taxfree configuration, net price is 129,79 => (30% off discount from 432,64)' => [
                "0.00",
                CartPrice::TAX_STATE_FREE,
                'EUR',
                $this->createOrderLineItemEntity(1, 129.79, 129.79, 0.0, 7.0, CartPrice::TAX_STATE_FREE)
            ],
            'net price configuration, net price is 129,79 => (30% off discount from 432,64)' => [
                "9.08",
                CartPrice::TAX_STATE_NET,
                'EUR',
                $this->createOrderLineItemEntity(1, 129.79, 129.79, 9.08, 7.0)
            ],
            'gross price configuration, 13.68 gross price other taxAmount' => [
                "1.89",
                CartPrice::TAX_STATE_GROSS,
                'EUR',
                $this->createOrderLineItemEntity(1, 1.1368, 13.68, 1.88, 16)
            ],
        ];
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
