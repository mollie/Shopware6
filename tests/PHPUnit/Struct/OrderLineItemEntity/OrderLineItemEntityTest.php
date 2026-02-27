<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Struct\OrderLineItemEntity;

use Kiener\MolliePayments\Struct\OrderLineItemEntity\OrderLineItemEntityAttributes;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;

class OrderLineItemEntityTest extends TestCase
{
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
            'composition' => [],
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
            'discountType' => [],
        ]);

        $attributes = new OrderLineItemEntityAttributes($item);

        $this->assertEquals(true, $attributes->isPromotion());
    }
}
