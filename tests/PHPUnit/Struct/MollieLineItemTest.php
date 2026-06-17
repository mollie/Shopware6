<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Struct;

use Kiener\MolliePayments\Struct\LineItemPriceStruct;
use Kiener\MolliePayments\Struct\MollieLineItem;
use PHPUnit\Framework\TestCase;

class MollieLineItemTest extends TestCase
{
    /**
     * This test verifies that we get a correct empty
     * array of metadata if nothing has been added.
     */
    public function testMetaDataEmptyIfNotConfigured()
    {
        $item = new MollieLineItem(
            '',
            '',
            1,
            new LineItemPriceStruct(1, 1, 0, 0),
            '',
            '',
            '',
            '',
        );

        $this->assertCount(0, $item->getMetaData());
    }

    /**
     * This test verifies that we can successfully add
     * custom metadata key and values and access them later on.
     */
    public function testMetaDataCanBeAdded()
    {
        $item = new MollieLineItem(
            '',
            '',
            1,
            new LineItemPriceStruct(1, 1, 0, 0),
            '',
            '',
            '',
            '',
        );

        $item->addMetaData('type', 'rounding');

        $this->assertEquals('rounding', $item->getMetaData()['type']);
    }
}
