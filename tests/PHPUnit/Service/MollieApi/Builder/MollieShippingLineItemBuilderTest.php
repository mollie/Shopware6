<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder;

use Kiener\MolliePayments\Hydrator\MollieLineItemHydrator;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieShippingLineItemBuilder;
use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Mollie\Api\Types\OrderLineType;
use MolliePayments\Tests\Traits\OrderTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;

class MollieShippingLineItemBuilderTest extends TestCase
{
    use OrderTrait;

    /** @var MollieShippingLineItemBuilder */
    private $builder;

    public function setUp(): void
    {
        $this->builder = new MollieShippingLineItemBuilder(
            (new PriceCalculator()),
            (new MollieOrderPriceBuilder())
        );
    }

    public function testBuildShippingLineItemsWithEmptyLineItems(): void
    {
        $lineItems = new OrderDeliveryCollection();
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');

        $expected = [];

        $hydrator = new MollieLineItemHydrator(new MollieOrderPriceBuilder());

        $actual = $hydrator->hydrate(
            $this->builder->buildShippingLineItems(CartPrice::TAX_STATE_GROSS, $lineItems),
            $currency->getIsoCode()
        );

        self::assertSame($expected, $actual);
    }

    public function testWithOneLineItem(): void
    {
        $deliveries = new OrderDeliveryCollection();

        $taxAmount = 7.5;
        $taxRate = 50.0;
        $totalPrice = 15.0;
        $deliveryId = Uuid::randomHex();

        $delivery = $this->getOrderDelivery($deliveryId, $taxAmount, $taxRate, $totalPrice);
        $deliveries->add($delivery);

        $isoCode = 'EUR';
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($isoCode);

        $expected = [[
            'type' => OrderLineType::TYPE_SHIPPING_FEE,
            'name' => 'Delivery costs 1',
            'quantity' => 1,
            'unitPrice' => [
                'currency' => $isoCode,
                'value' => '15.00'
            ],
            'totalAmount' => [
                'currency' => $isoCode,
                'value' => '15.00'
            ],
            'vatRate' => '50.00',
            'vatAmount' => [
                'currency' => $isoCode,
                'value' => '5.00'
            ],
            'sku' => 'mol-delivery-1',
            'imageUrl' => '',
            'productUrl' => '',
            'metadata' => [
                'orderLineItemId' => $deliveryId
            ]
        ]];

        $hydrator = new MollieLineItemHydrator(new MollieOrderPriceBuilder());

        $actual = $hydrator->hydrate($this->builder->buildShippingLineItems(CartPrice::TAX_STATE_GROSS, $deliveries), $currency->getIsoCode());

        self::assertSame($expected, $actual);
    }
}
