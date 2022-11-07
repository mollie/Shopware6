<?php

namespace MolliePayments\Tests\Components\RefundManager\RefundData\OrderItem;

use Kiener\MolliePayments\Components\RefundManager\RefundData\OrderItem\ProductItem;
use MolliePayments\Tests\Traits\OrderTrait;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;


class ProductItemTest extends TestCase
{
    use OrderTrait;

    /**
     * @var OrderLineItemEntity
     */
    private $lineItem;


    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->lineItem = $this->getOrderLineItem(
            'ITEM-123',
            'NUMBER-123',
            'T-Shirt',
            1,
            4.9,
            19,
            4,
            'physical',
            '',
            '',
            1
        );
    }


    /**
     * This test verifies that the payload array is
     * correctly built from our ProductItem object.
     *
     * @return void
     */
    public function testToArray(): void
    {
        $refundManagerItem = new ProductItem($this->lineItem, [], 0);

        $expected = [
            'refunded' => 0,
            'shopware' => [
                'id' => 'ITEM-123',
                'label' => 'T-Shirt',
                'unitPrice' => 4.9,
                'quantity' => 1,
                'totalPrice' => 4.9,
                'discountedPrice' => 4.9,
                'productNumber' => 'NUMBER-123',
                'promotion' => [
                    'discount' => 0,
                    'quantity' => 0,
                ],
                'isPromotion' => false,
                'isDelivery' => false,
            ],
        ];

        $this->assertEquals($expected, $refundManagerItem->toArray());
    }

}
