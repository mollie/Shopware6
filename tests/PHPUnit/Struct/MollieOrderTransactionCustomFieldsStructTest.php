<?php declare(strict_types=1);

namespace MolliePayments\Tests\Struct;

use PHPUnit\Framework\TestCase;
use Kiener\MolliePayments\Struct\MollieOrderTransactionCustomFieldsStruct;

class MollieOrderTransactionCustomFieldsStructTest extends TestCase
{
    /**
     * This test verifies that nothing breaks if nothing is
     * passed while creating the struct.
     */
    public function testEmptyArrayCreation()
    {
        $struct = new MollieOrderTransactionCustomFieldsStruct();
        $this->assertEquals(['mollie_payments' => []], $struct->getMollieCustomFields());
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
        $struct = new MollieOrderTransactionCustomFieldsStruct([
            'mollie_payments' => [
                'order_id' => 'order_id',
                'payment_id' => 'payment_id',
                'third_party_payment_id' => 'third_party_payment_id'
            ],
        ]);

        $this->assertEquals(['mollie_payments' => [
            'order_id' => 'order_id',
            'payment_id' => 'payment_id',
            'third_party_payment_id' => 'third_party_payment_id'
        ]], $struct->getMollieCustomFields());
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
        $struct = new MollieOrderTransactionCustomFieldsStruct([
            'mollie_payments' => [
                'payment_id' => 'payment_id',
            ],
        ]);

        $this->assertEquals(['mollie_payments' => []], $struct->getMollieCustomFields());
    }
}