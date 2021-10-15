<?php declare(strict_types=1);

namespace MolliePayments\Tests\Struct\LineItem;

use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class OrderLineItemEntityTest extends TestCase
{

    /**
     *
     */
    public function testNullPayload()
    {
        $item = new OrderLineItemEntity();

        $attributes = new OrderLineItemEntityAttributes($item);

        $this->assertEquals('', $attributes->getVoucherType());
    }

    /**
     *
     */
    public function testEmptyCustomFields()
    {
        $item = new OrderLineItemEntity();
        $item->setPayload([
            'customFields' => []
        ]);

        $attributes = new OrderLineItemEntityAttributes($item);

        $this->assertEquals('', $attributes->getVoucherType());
    }

    /**
     *
     */
    public function testEmptyMolliePayments()
    {
        $item = new OrderLineItemEntity();
        $item->setPayload([
            'customFields' => [
                'mollie_payments' => [

                ]
            ]
        ]);

        $attributes = new OrderLineItemEntityAttributes($item);

        $this->assertEquals('', $attributes->getVoucherType());
    }

    /**
     *
     */
    public function testVoucherType()
    {
        $item = new OrderLineItemEntity();
        $item->setPayload([
            'customFields' => [
                'mollie_payments' => [
                    'voucher_type' => '2',
                ]
            ]
        ]);

        $attributes = new OrderLineItemEntityAttributes($item);

        $this->assertEquals('2', $attributes->getVoucherType());
    }

}
