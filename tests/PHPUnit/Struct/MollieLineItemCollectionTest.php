<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Struct;

use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Kiener\MolliePayments\Struct\MollieLineItem;
use Kiener\MolliePayments\Struct\MollieLineItemCollection;
use PHPUnit\Framework\TestCase;

class MollieLineItemCollectionTest extends TestCase
{
    /**
     * This test verifies that our total amount of the cart
     * is always correctly calculated from the sum of items.
     *
     * @return void
     */
    public function testCartTotalAmount()
    {
        $items = new MollieLineItemCollection([
            new MollieLineItem(
                '',
                '',
                1,
                new LineItemPriceStruct(2.73, 2.73, 0.44, 19),
                '',
                '',
                '',
                ''
            ),
            new MollieLineItem(
                '',
                '',
                2,
                new LineItemPriceStruct(1, 2, 0.47, 19),
                '',
                '',
                '',
                ''
            ),
        ]);

        $this->assertEquals(4.73, $items->getCartTotalAmount());
    }
}
