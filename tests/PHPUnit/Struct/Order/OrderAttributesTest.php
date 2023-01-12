<?php


namespace MolliePayments\Tests\Struct\Order;

use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;

class OrderAttributesTest extends TestCase
{

    /**
     * @return void
     */
    public function testIsSubscription()
    {
        $order = new OrderEntity();

        $attributes = new OrderAttributes($order);

        $this->assertEquals(false, $attributes->isTypeSubscription());
    }

    /**
     * @return void
     */
    public function testIsSubscriptionWithMollieId()
    {
        $order = new OrderEntity();
        $order->setCustomFields([
            'mollie_payments' => [
                'mollieSubscriptionId' => 'sub_xyz',
            ]
        ]);

        $attributes = new OrderAttributes($order);

        $this->assertEquals(true, $attributes->isTypeSubscription());
    }

    /**
     * @return void
     */
    public function testIsSubscriptionWithShopwareId()
    {
        $order = new OrderEntity();
        $order->setCustomFields([
            'mollie_payments' => [
                'swSubscriptionId' => '1231244142',
            ]
        ]);

        $attributes = new OrderAttributes($order);

        $this->assertEquals(true, $attributes->isTypeSubscription());
    }

}
