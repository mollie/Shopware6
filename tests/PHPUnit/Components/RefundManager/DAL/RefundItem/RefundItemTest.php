<?php

namespace MolliePayments\Tests\Components\RefundManager\DAL\RefundItem;

use Kiener\MolliePayments\Components\RefundManager\DAL\RefundItem\RefundItemEntity;
use PHPUnit\Framework\TestCase;

class RefundItemTest extends TestCase
{

    /**
     * This test verifies that our DAL payload is correct.
     * It covers the case where we don't provide an additional refundId as foreign key.
     * @return void
     */
    public function testDalPayloadWithoutRefundId(): void
    {
        $payload = RefundItemEntity::createArray(
            'mol-1',
            'T-Shirt',
            2,
            2.49,
            'ord-id-1',
            'ord-id-version-1',
            null
        );

        $expected = [
            'mollieLineId' => 'mol-1',
            'label' => 'T-Shirt',
            'quantity' => 2,
            'amount' => 2.49,
            'orderLineItemId' => 'ord-id-1',
            'orderLineItemVersionId' => 'ord-id-version-1',
        ];

        $this->assertEquals($expected, $payload);
    }

    /**
     * This test verifies that our DAL payload is correct.
     * It covers the case where we also provide an additional refundId as foreign key.
     * @return void
     */
    public function testDalPayloadWithRefundId(): void
    {
        $payload = RefundItemEntity::createArray(
            'mol-1',
            'T-Shirt',
            2,
            2.49,
            'ord-id-1',
            'ord-id-version-1',
            'refund-1'
        );

        $this->assertEquals('refund-1', $payload['refundId']);
    }

    /**
     * This test verifies that our DAL payload is correct.
     * It covers the case where we provide an empty string as refund ID and it should not be added in that case.
     * @return void
     */
    public function testDALPayloadNoRefundIfEmpty(): void
    {
        $payload = RefundItemEntity::createArray(
            'mol-1',
            'T-Shirt',
            2,
            2.49,
            'ord-id-1',
            'ord-id-version-1',
            ''
        );

        $this->assertArrayNotHasKey('refundId', $payload);
    }

}
