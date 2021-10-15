<?php declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder;

use Kiener\MolliePayments\Factory\CompatibilityFactory;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieLineItemBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\MollieApi\LineItemDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Validator\IsOrderLineItemValid;
use Mollie\Api\Types\OrderLineType;
use MolliePayments\Tests\Fakes\FakeCompatibilityGateway;
use MolliePayments\Tests\Traits\OrderTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;

class MollieLineItemBuilderTest extends TestCase
{
    use OrderTrait;

    /** @var MollieLineItemBuilder */
    private $builder;

    public function setUp(): void
    {
        $this->builder = new MollieLineItemBuilder(
            (new MollieOrderPriceBuilder()),
            (new IsOrderLineItemValid()),
            (new PriceCalculator()),
            (new LineItemDataExtractor()),
            new FakeCompatibilityGateway()
        );
    }

    public function testConstants(): void
    {
        self::assertSame('customized-products', MollieLineItemBuilder::LINE_ITEM_TYPE_CUSTOM_PRODUCTS);
    }

    public function testBuildLineItemsWithEmptyLineItems(): void
    {
        $lineItems = new OrderLineItemCollection();
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');

        $expected = [];

        self::assertSame($expected, $this->builder->buildLineItems(CartPrice::TAX_STATE_GROSS, $lineItems, $currency));
    }

    public function testBuildLineItemsWithNullLineItemCollection(): void
    {
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');

        $expected = [];

        self::assertSame($expected, $this->builder->buildLineItems(CartPrice::TAX_STATE_GROSS, null, $currency));
    }

    public function testWithOneLineItem(): void
    {
        $lineItems = new OrderLineItemCollection();
        $productNumber = 'foo';
        $labelName = 'bar';
        $quantity = 1;
        $taxRate = 50.0;
        $unitPrice = 15.0;
        $lineItemId = Uuid::randomHex();
        $seoUrl = 'http://seoUrl';
        $imageUrl = 'http://imageUrl';

        $isoCode = 'EUR';
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($isoCode);

        $lineItem = $this->getOrderLineItem(
            $lineItemId,
            $productNumber,
            $labelName,
            $quantity,
            $unitPrice,
            $taxRate,
            1.0,
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $seoUrl,
            $imageUrl
        );

        $lineItems->add($lineItem);

        $expected = [[
            'type' => OrderLineType::TYPE_PHYSICAL,
            'name' => $labelName,
            'quantity' => $quantity,
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
            'sku' => $productNumber,
            'imageUrl' => urlencode($imageUrl),
            'productUrl' => urlencode($seoUrl),
            'metadata' => [
                'orderLineItemId' => $lineItemId
            ]
        ]];

        self::assertSame($expected, $this->builder->buildLineItems(CartPrice::TAX_STATE_GROSS, $lineItems, $currency));
    }

    public function testEmptySeoUrlAndImageUrl(): void
    {
        $lineItems = new OrderLineItemCollection();
        $productNumber = 'foo';
        $labelName = 'bar';
        $quantity = 1;
        $taxRate = 50.0;
        $unitPrice = 15.0;
        $lineItemId = Uuid::randomHex();
        $seoUrl = '';
        $imageUrl = '';

        $isoCode = 'USD';
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($isoCode);

        $lineItem = $this->getOrderLineItem(
            $lineItemId,
            $productNumber,
            $labelName,
            $quantity,
            $unitPrice,
            $taxRate,
            1.0,
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $seoUrl,
            $imageUrl
        );

        $lineItems->add($lineItem);

        $expected = [[
            'type' => OrderLineType::TYPE_PHYSICAL,
            'name' => $labelName,
            'quantity' => $quantity,
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
            'sku' => $productNumber,
            'imageUrl' => urlencode($imageUrl),
            'productUrl' => urlencode($seoUrl),
            'metadata' => [
                'orderLineItemId' => $lineItemId
            ]
        ]];

        self::assertSame($expected, $this->builder->buildLineItems(CartPrice::TAX_STATE_GROSS, $lineItems, $currency));
    }

    public function testCreditLineItemType(): void
    {
        $lineItems = new OrderLineItemCollection();
        $productNumber = 'foo';
        $labelName = 'bar';
        $quantity = 1;
        $taxRate = 50.0;
        $unitPrice = 15.0;
        $lineItemId = Uuid::randomHex();
        $seoUrl = '';
        $imageUrl = '';

        $lineItem = $this->getOrderLineItem(
            $lineItemId,
            $productNumber,
            $labelName,
            $quantity,
            $unitPrice,
            $taxRate,
            1.0,
            LineItem::CREDIT_LINE_ITEM_TYPE,
            $seoUrl,
            $imageUrl
        );

        $lineItems->add($lineItem);
        $isoCode = 'USD';
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($isoCode);

        $expected = [[
            'type' => OrderLineType::TYPE_STORE_CREDIT,
            'name' => $labelName,
            'quantity' => $quantity,
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
            'sku' => $productNumber,
            'imageUrl' => urlencode($imageUrl),
            'productUrl' => urlencode($seoUrl),
            'metadata' => [
                'orderLineItemId' => $lineItemId
            ]
        ]];

        self::assertSame($expected, $this->builder->buildLineItems(CartPrice::TAX_STATE_GROSS, $lineItems, $currency));
    }

    public function testPromotionLineItemType(): void
    {
        $lineItems = new OrderLineItemCollection();
        $productNumber = 'foo';
        $labelName = 'bar';
        $quantity = 1;
        $taxRate = 50.0;
        $unitPrice = 15.0;
        $lineItemId = Uuid::randomHex();
        $seoUrl = '';
        $imageUrl = '';

        $lineItem = $this->getOrderLineItem(
            $lineItemId,
            $productNumber,
            $labelName,
            $quantity,
            $unitPrice,
            $taxRate,
            1.0,
            LineItem::PROMOTION_LINE_ITEM_TYPE,
            $seoUrl,
            $imageUrl
        );

        $lineItems->add($lineItem);
        $isoCode = 'USD';
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($isoCode);

        $expected = [[
            'type' => OrderLineType::TYPE_DISCOUNT,
            'name' => $labelName,
            'quantity' => $quantity,
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
            'sku' => $productNumber,
            'imageUrl' => urlencode($imageUrl),
            'productUrl' => urlencode($seoUrl),
            'metadata' => [
                'orderLineItemId' => $lineItemId
            ]
        ]];

        self::assertSame($expected, $this->builder->buildLineItems(CartPrice::TAX_STATE_GROSS, $lineItems, $currency));
    }

    public function testCustomProductsLineItemType(): void
    {
        $lineItems = new OrderLineItemCollection();
        $productNumber = 'foo';
        $labelName = 'bar';
        $quantity = 1;
        $taxRate = 50.0;
        $unitPrice = 15.0;
        $lineItemId = Uuid::randomHex();
        $seoUrl = '';
        $imageUrl = '';

        $lineItem = $this->getOrderLineItem(
            $lineItemId,
            $productNumber,
            $labelName,
            $quantity,
            $unitPrice,
            $taxRate,
            1.0,
            MollieLineItemBuilder::LINE_ITEM_TYPE_CUSTOM_PRODUCTS,
            $seoUrl,
            $imageUrl
        );

        $lineItems->add($lineItem);
        $isoCode = 'USD';
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($isoCode);

        $expected = [[
            'type' => OrderLineType::TYPE_PHYSICAL,
            'name' => $labelName,
            'quantity' => $quantity,
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
            'sku' => $productNumber,
            'imageUrl' => urlencode($imageUrl),
            'productUrl' => urlencode($seoUrl),
            'metadata' => [
                'orderLineItemId' => $lineItemId
            ]
        ]];

        self::assertSame($expected, $this->builder->buildLineItems(CartPrice::TAX_STATE_GROSS, $lineItems, $currency));
    }

    public function testFallbackLineItemType(): void
    {
        $lineItems = new OrderLineItemCollection();
        $productNumber = 'foo';
        $labelName = 'bar';
        $quantity = 1;
        $taxRate = 50.0;
        $unitPrice = 15.0;
        $lineItemId = Uuid::randomHex();
        $seoUrl = '';
        $imageUrl = '';

        $lineItem = $this->getOrderLineItem(
            $lineItemId,
            $productNumber,
            $labelName,
            $quantity,
            $unitPrice,
            $taxRate,
            1.0,
            'foo',
            $seoUrl,
            $imageUrl
        );

        $lineItems->add($lineItem);
        $isoCode = 'USD';
        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode($isoCode);

        $expected = [[
            'type' => OrderLineType::TYPE_DIGITAL,
            'name' => $labelName,
            'quantity' => $quantity,
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
            'sku' => $productNumber,
            'imageUrl' => urlencode($imageUrl),
            'productUrl' => urlencode($seoUrl),
            'metadata' => [
                'orderLineItemId' => $lineItemId
            ]
        ]];

        self::assertSame($expected, $this->builder->buildLineItems(CartPrice::TAX_STATE_GROSS, $lineItems, $currency));
    }
}
