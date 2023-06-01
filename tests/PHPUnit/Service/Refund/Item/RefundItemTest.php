<?php

namespace MolliePayments\Tests\Service\Refund\Item;

use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Kiener\MolliePayments\Service\Refund\Item\RefundItemType;
use PHPUnit\Framework\TestCase;

class RefundItemTest extends TestCase
{
    /**
     * This is the data that is sent through our service to Mollie.
     * It's not the refund amount, but only the composition of the refund.
     * We want to make sure that we remove ugly float numbers and round
     * the amount automatically when creating this object.
     *
     * @return void
     */
    public function testAmountRounded()
    {
        $item = new RefundItem(
            '',
            '',
            '',
            1,
            14.9899999,
        );

        $this->assertEquals(14.99, $item->getAmount());
    }
}
