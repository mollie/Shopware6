<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Struct\OrderTransaction;

use Kiener\MolliePayments\Struct\OrderTransaction\OrderTransactionAttributes;
use PHPUnit\Framework\TestCase;

class OrderTransactionAttributesTest extends TestCase
{
    /**
     * This test verifies that nothing breaks if nothing is
     * passed while creating the struct.
     */
    public function testEmptyArrayCreation()
    {
        $struct = new OrderTransactionAttributes();

        $this->assertEquals([], $struct->toArray());
        $this->assertEquals('', $struct->getMollieOrderId());
        $this->assertEquals('', $struct->getMolliePaymentId());
        $this->assertEquals('', $struct->getThirdPartyPaymentId());
    }

    /**
     * This test verifies that the struct can be created with
     * an array as input.
     */
    public function testArrayCreation()
    {
        $struct = new OrderTransactionAttributes([
            'mollie_payments' => [
                'order_id' => 'order_id',
                'payment_id' => 'payment_id',
                'third_party_payment_id' => 'third_party_payment_id',
            ],
        ]);

        $this->assertEquals(
            [
                'order_id' => 'order_id',
                'payment_id' => 'payment_id',
                'third_party_payment_id' => 'third_party_payment_id',
            ],
            $struct->toArray()
        );

        $this->assertEquals('order_id', $struct->getMollieOrderId());
        $this->assertEquals('payment_id', $struct->getMolliePaymentId());
        $this->assertEquals('third_party_payment_id', $struct->getThirdPartyPaymentId());
    }

    /**
     * This test verifies that an empty array is returned when
     * no order ID is set.
     */
    public function testEmptyOrderId()
    {
        $struct = new OrderTransactionAttributes([
            'mollie_payments' => [
                'payment_id' => 'payment_id',
            ],
        ]);

        $this->assertEquals([], $struct->toArray());
    }
}
