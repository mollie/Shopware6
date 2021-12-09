<?php declare(strict_types=1);

namespace MolliePayments\Tests\Struct\OrderLineItemEntity;

use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use Kiener\MolliePayments\Struct\Voucher\VoucherType;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class OrderLineItemEntityTest extends TestCase
{

    /**
     * This test verifies that nothing breaks if we
     * have NULL instead of a payload array.
     */
    public function testNullPayload()
    {
        $item = new OrderLineItemEntity();

        $attributes = new OrderLineItemEntityAttributes($item);

        $this->assertEquals('', $attributes->getVoucherType());
    }

    /**
     * This test verifies that nothing breaks
     * if we only have an empty list as custom fields.
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
     * This test verifies that we have default values
     * if our mollie data struct is empty.
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
     * This test verifies that an existing voucher type entry
     * is correctly loaded from our attributes class.
     */
    public function testVoucherType()
    {
        $item = new OrderLineItemEntity();
        $item->setPayload([
            'customFields' => [
                'mollie_payments' => [
                    'voucher_type' => VoucherType::TYPE_MEAL,
                ]
            ]
        ]);

        $attributes = new OrderLineItemEntityAttributes($item);

        $this->assertEquals(VoucherType::TYPE_MEAL, $attributes->getVoucherType());
    }

}
