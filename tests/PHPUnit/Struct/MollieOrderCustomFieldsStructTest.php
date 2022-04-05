<?php declare(strict_types=1);

namespace MolliePayments\Tests\Struct;

use PHPUnit\Framework\TestCase;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Shopware\Core\Checkout\Order\OrderEntity;

class MollieOrderCustomFieldsStructTest extends TestCase
{
    /**
     * This test verifies that nothing breaks if nothing is
     * passed while creating the struct.
     */
    public function testEmptyArrayCreation()
    {
        $order = new OrderEntity();

        $struct = new OrderAttributes($order);
        $this->assertEquals(['mollie_payments' => []], $struct->toArray());
        $this->assertEquals('', $struct->getMollieOrderId());
        $this->assertEquals('', $struct->getMolliePaymentId());
        $this->assertEquals('', $struct->getTransactionReturnUrl());
        $this->assertEquals('', $struct->getMolliePaymentUrl());
    }

    /**
     * This test verifies that the struct can be created with
     * an array as input.
     */
    public function testArrayCreation()
    {
        $order = new OrderEntity();
        $order->setCustomFields(
            [
                'mollie_payments' => [
                    'order_id' => 'order_id',
                    'payment_id' => 'payment_id',
                    'third_party_payment_id' => 'third_party_payment_id',
                    'transactionReturnUrl' => 'transactionReturnUrl',
                    'molliePaymentUrl' => 'molliePaymentUrl',
                    'swSubscriptionId' => '12345',
                    'mollieSubscriptionId' => 'sub_123',
                ],
            ]
        );

        $struct = new OrderAttributes($order);


        $this->assertEquals([
            'mollie_payments' => [
                'order_id' => 'order_id',
                'payment_id' => 'payment_id',
                'third_party_payment_id' => 'third_party_payment_id',
                'transactionReturnUrl' => 'transactionReturnUrl',
                'molliePaymentUrl' => 'molliePaymentUrl',
                'swSubscriptionId' => '12345',
                'mollieSubscriptionId' => 'sub_123',
            ]
        ], $struct->toArray());
        $this->assertEquals('order_id', $struct->getMollieOrderId());
        $this->assertEquals('payment_id', $struct->getMolliePaymentId());
        $this->assertEquals('third_party_payment_id', $struct->getThirdPartyPaymentId());
        $this->assertEquals('transactionReturnUrl', $struct->getTransactionReturnUrl());
        $this->assertEquals('molliePaymentUrl', $struct->getMolliePaymentUrl());
    }

    /**
     * This test verifies that an empty array is returned when
     * no order ID is set.
     */
    public function testEmptyOrderId()
    {
        $order = new OrderEntity();
        $order->setCustomFields(
            [
                'other_data' => '1',
                'mollie_payments' => [],
            ]
        );

        $struct = new OrderAttributes($order);

        $expected = ['mollie_payments' => [],
        ];

        $this->assertEquals($expected, $struct->toArray());
    }
}