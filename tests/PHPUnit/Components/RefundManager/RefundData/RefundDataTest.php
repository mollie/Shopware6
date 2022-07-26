<?php

namespace MolliePayments\Tests\Components\RefundManager\RefundData;

use Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem\ProductItem;
use Kiener\MolliePayments\Components\RefundManager\RefundData\RefundData;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Refund;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;


class RefundDataTest extends TestCase
{


    /**
     * This test verifies that the array format
     * of our total values are correct.
     *
     * @return void
     */
    public function testTotalValues()
    {
        $data = new RefundData([], [], 5, 2, 6, 9);

        $expected = [
            'totals' => [
                'remaining' => 9.0,
                'voucherAmount' => 5.0,
                'pendingRefunds' => 2.0,
                'refunded' => 6.0,
            ],
            'cart' => [],
            'refunds' => [],
        ];

        $this->assertEquals($expected, $data->toArray());
    }

    /**
     * This test verifies that the array format
     * of our total values are correct.
     *
     * @return void
     */
    public function testCartItems()
    {
        $items = [];

        $lineItem = new OrderLineItemEntity();
        $lineItem->setId('line-1');
        $lineItem->setLabel('Product 1');
        $lineItem->setUnitPrice(19.99);
        $lineItem->setQuantity(2);
        $lineItem->setTotalPrice(2 * 19.99);
        $lineItem->setReferencedId('product-id-1');
        $lineItem->setPayload(['productNumber' => 'P123']);
        $lineItem->setCustomFields([
            'mollie_payments' => [
                'reset_stock_quantity' => 2
            ],
        ]);

        $items[] = new ProductItem($lineItem, [], 2);

        $data = new RefundData($items, [], 0, 0, 0, 0);

        $expected = [
            [
                'refunded' => 2,
                'shopware' => [
                    'id' => 'line-1',
                    'label' => 'Product 1',
                    'unitPrice' => 19.99,
                    'quantity' => 2,
                    'totalPrice' => 2 * 19.99,
                    'discountedPrice' => 2 * 19.99,
                    'productNumber' => 'P123',
                    'promotion' => [
                        'discount' => 0.0,
                        'quantity' => 0,
                    ],
                    'isPromotion' => false,
                    'isDelivery' => false,
                    'resetStockQuantity' => 2
                ],
            ]
        ];

        $this->assertEquals($expected, $data->toArray()['cart']);
    }

    /**
     * This test verifies that the array contains
     * our refund from Mollie. We pass on the structure
     * directly, so we only check the count in here.
     *
     * @return void
     */
    public function testRefundsCount()
    {
        $refunds = [];

        $refunds[] = new Refund(new MollieApiClient());

        $data = new RefundData([], $refunds, 0, 0, 0, 0);

        $this->assertCount(1, $data->toArray()['refunds']);
    }

}
