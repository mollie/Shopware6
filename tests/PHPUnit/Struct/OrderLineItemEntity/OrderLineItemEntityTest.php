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
     * This test verifies that nothing breaks
     * if we have NULL for the customFields
     */
    public function testNullCustomFields()
    {
        $item = new OrderLineItemEntity();
        $item->setPayload([
            'customFields' => null,
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

    /**
     * This test verifies that regular items are not recognized as promotion items.@
     */
    public function testRegularProductIsNoPromotion()
    {
        $item = new OrderLineItemEntity();
        $item->setPayload(null);

        $attributes = new OrderLineItemEntityAttributes($item);

        $this->assertEquals(false, $attributes->isPromotion());
    }

    /**
     * This test verifies that promotions are correctly recognized.
     * We use the composition payload entry for it.
     */
    public function testPromotionIsRecognized()
    {
        $item = new OrderLineItemEntity();
        $item->setPayload([
            'composition' => []
        ]);

        $attributes = new OrderLineItemEntityAttributes($item);

        $this->assertEquals(true, $attributes->isPromotion());
    }

    /**
     * This test verifies that a shipping free promotion is correctly recognized.
     * A shipping free promotion has no composition, but only a discount type.
     */
    public function testShippingFreePromotionIsRecognized()
    {
        $item = new OrderLineItemEntity();
        $item->setPayload([
            'discountType' => []
        ]);

        $attributes = new OrderLineItemEntityAttributes($item);

        $this->assertEquals(true, $attributes->isPromotion());
    }
}
