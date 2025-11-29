<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service\Refund\Item;

use Kiener\MolliePayments\Service\Refund\Item\RefundItemType;
use PHPUnit\Framework\TestCase;

class RefundItemTypeTest extends TestCase
{
    /**
     * This test verifies that our constant is not changed.
     * It will be used in the meta data of the refund inside Mollie.
     * This is crucial to decide whether or not refunded quantities need to be
     * included in our calculation or not! Please see usage of this constant for more!
     *
     * @return void
     */
    public function testTypeFull()
    {
        $this->assertEquals('full', RefundItemType::FULL);
    }

    /**
     * This test verifies that our constant is not changed.
     * It will be used in the meta data of the refund inside Mollie.
     * This is crucial to decide whether or not refunded quantities need to be
     * included in our calculation or not! Please see usage of this constant for more!
     *
     * @return void
     */
    public function testTypePartial()
    {
        $this->assertEquals('partial', RefundItemType::PARTIAL);
    }
}
