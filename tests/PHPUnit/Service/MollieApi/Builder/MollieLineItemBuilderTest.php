<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi\Builder;

use Kiener\MolliePayments\Hydrator\MollieLineItemHydrator;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieLineItemBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieOrderPriceBuilder;
use Kiener\MolliePayments\Service\MollieApi\Builder\MollieShippingLineItemBuilder;
use Kiener\MolliePayments\Service\MollieApi\Fixer\RoundingDifferenceFixer;
use Kiener\MolliePayments\Service\MollieApi\LineItemDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\PriceCalculator;
use Kiener\MolliePayments\Service\UrlParsingService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Validator\IsOrderLineItemValid;
use Mollie\Api\Types\OrderLineType;
use MolliePayments\Tests\Fakes\FakeCompatibilityGateway;
use MolliePayments\Tests\Traits\OrderTrait;
use MolliePayments\Tests\Utils\Traits\PaymentBuilderTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;

class MollieLineItemBuilderTest extends TestCase
{
    use OrderTrait;
    use PaymentBuilderTrait;

    /**
     * @var MollieLineItemBuilder
     */
    private $builder;

    public function setUp(): void
    {
        $this->builder = new MollieLineItemBuilder(
            (new IsOrderLineItemValid()),
            (new PriceCalculator()),
            (new LineItemDataExtractor(new UrlParsingService())),
            new FakeCompatibilityGateway(),
            new RoundingDifferenceFixer(),
            new MollieLineItemHydrator(new MollieOrderPriceBuilder()),
            new MollieShippingLineItemBuilder(new PriceCalculator())
        );
    }

    /**
     * This test verifies that our constant for custom products is not touched.
     */
    public function testConstants(): void
    {
        self::assertSame('customized-products', MollieLineItemBuilder::LINE_ITEM_TYPE_CUSTOM_PRODUCTS);
    }

    /**
     * This test verifies that an empty order item list leads to
     * an empty array.
     */
    public function testNoLineItems(): void
    {
        $order = $this->getOrderEntity(
            0,
            '',
            'EUR',
            new OrderLineItemCollection([]),
            ''
        );

        $settings = new MollieSettingStruct();

        $lines = $this->builder->buildLineItemPayload($order, 'EUR', $settings, false);

        self::assertEquals([], $lines);
    }

    /**
     * @return array<mixed>[]
     */
    public function getStructureData(): array
    {
        return [
            'PRODUCT Line Item is PHYSICAL' => [LineItem::PRODUCT_LINE_ITEM_TYPE, OrderLineType::TYPE_PHYSICAL],
            'CREDIT Line Item is PHYSICAL' => [LineItem::CREDIT_LINE_ITEM_TYPE, OrderLineType::TYPE_STORE_CREDIT],
            'PROMOTION Line Item is PHYSICAL' => [LineItem::PROMOTION_LINE_ITEM_TYPE, OrderLineType::TYPE_DISCOUNT],
            'CUSTOM_PRODUCT Line Item is PHYSICAL' => [MollieLineItemBuilder::LINE_ITEM_TYPE_CUSTOM_PRODUCTS, OrderLineType::TYPE_PHYSICAL],
            'Fallback Line Item is DIGITAL' => ['test', OrderLineType::TYPE_DIGITAL],
        ];
    }

    /**
     * This test verifies that our structure of a single line item is correct
     * and working for the Mollie API as payload.
     *
     * @dataProvider getStructureData
     */
    public function testLineItemStructure(string $itemType, string $mollieLineType): void
    {
        $item1 = $this->getOrderLineItem(
            'line-1',
            'product-123',
            'Product T-Shirt',
            1,
            4.9,
            19,
            0,
            $itemType,
            'https://phpunit.mollie.local/my-product-1',
            'https://phpunit.mollie.local/my-product-1.png',
            1
        );

        $order = $this->getOrderEntity(
            4.99,
            '',
            'EUR',
            new OrderLineItemCollection([$item1]),
            ''
        );

        $settings = new MollieSettingStruct();

        $lineItems = $this->builder->buildLineItemPayload($order, 'EUR', $settings, false);

        $expected = [
            'type' => $mollieLineType,
            'name' => $item1->getLabel(),
            'quantity' => 1,
            'unitPrice' => [
                'currency' => 'EUR',
                'value' => '4.90',
            ],
            'totalAmount' => [
                'currency' => 'EUR',
                'value' => '4.90',
            ],
            'vatRate' => '19.00',
            'vatAmount' => [
                'currency' => 'EUR',
                'value' => '0.78',
            ],
            'sku' => 'product-123',
            'imageUrl' => 'https://phpunit.mollie.local/my-product-1.png',
            'productUrl' => 'https://phpunit.mollie.local/my-product-1',
            'metadata' => [
                'orderLineItemId' => 'line-1',
            ],
        ];

        self::assertCount(1, $lineItems);
        self::assertEquals($expected, $lineItems[0]);
    }

    /**
     * This test verifies that line items with a higher precision do also work.
     * These need to be rounded to 2 decimals.
     * Usually there is a rounding difference. This is why we also have to fix the rounding so that the final sum is correct.
     * This is a real world scenario from a Github Issue: https://github.com/mollie/Shopware6/issues/439
     */
    public function testLineItemsWithHigherPrecision(): void
    {
        $item1 = $this->getOrderLineItem('1', '', '', 1, 2.7336, 19, 0, '', '', '', 1);
        $item2 = $this->getOrderLineItem('2', '', '', 1, 2.9334, 19, 0, '', '', '', 1);
        $item3 = $this->getOrderLineItem('3', '', '', 1, 1.6494, 19, 0, '', '', '', 1);
        $item4 = $this->getOrderLineItem('4', '', '', 1, 7.5, 19, 0, '', '', '', 1);

        $order = $this->getOrderEntity(
            14.82,
            '',
            'EUR',
            new OrderLineItemCollection([$item1, $item2, $item3, $item4]),
            ''
        );

        $settings = new MollieSettingStruct();
        $settings->setFixRoundingDiffEnabled(true);

        $lineItems = $this->builder->buildLineItemPayload($order, 'EUR', $settings, false);

        $lineItemSum = 0;
        foreach ($lineItems as $item) {
            $lineItemSum += (float) $item['totalAmount']['value'];
        }

        // make sure that our separate line items are correctly rounded
        // Mollie only allows 2 decimals
        $this->assertEquals('2.73', $lineItems[0]['totalAmount']['value']);
        $this->assertEquals('2.93', $lineItems[1]['totalAmount']['value']);
        $this->assertEquals('1.65', $lineItems[2]['totalAmount']['value']);
        $this->assertEquals('7.50', $lineItems[3]['totalAmount']['value']);
        $this->assertEquals('0.01', $lineItems[4]['totalAmount']['value']);

        $this->assertEquals(14.82, $lineItemSum);
    }

    /**
     * @return array[]
     */
    public function getRoundingFixerData(): array
    {
        return [
            'Enable Fixing, but not difference, items remain the same' => [true, 2.73, 2.73, 1, 2.73],
            'Enable Fixing with difference, diff item is added' => [true, 2.73, 3.00, 2, 3.0],
            'Disable Fixing with difference, items remain the same' => [false, 2.73, 3.00, 1, 2.73],
        ];
    }

    /**
     * This test verifies that we use the fixer algorithm if enabled, and do not use it if disabled.
     * The basic concept is that we either have a difference in the order-total compared to the line items,
     * and depending on our fixing configuration, a new (diff) line item is then added or not.
     * In the end the sum of the line items has to match our expected orderTotal amount.
     *
     * @dataProvider getRoundingFixerData
     *
     * @return void
     */
    public function testRoundingFixerIsUsedCorrectly(bool $fixRoundingIssues, float $swItemUnitPrice, float $swOrderTotal, int $lineItemCount, float $expectedOrderSum)
    {
        $settings = new MollieSettingStruct();
        $settings->setFixRoundingDiffEnabled($fixRoundingIssues);

        $item1 = $this->getOrderLineItem(
            '',
            '',
            '',
            1,
            $swItemUnitPrice,
            19,
            0,
            '',
            '',
            '',
            1
        );

        $order = $this->getOrderEntity(
            $swOrderTotal,
            '',
            '',
            new OrderLineItemCollection([$item1]),
            ''
        );

        $lineItems = $this->builder->buildLineItemPayload($order, 'EUR', $settings, true);

        // check if we have the expected number of items
        // this is either with, or without our diff-line-item
        $this->assertCount($lineItemCount, $lineItems);

        // now sum up the line items of the hydrated array
        // this has to match the values that we expect to be.
        $lineItemSum = 0;
        foreach ($lineItems as $item) {
            $lineItemSum += (float) $item['totalAmount']['value'];
        }

        $this->assertEquals($expectedOrderSum, $lineItemSum);
    }
}
