<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service\Refund\Mollie;

use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Kiener\MolliePayments\Service\Refund\Mollie\RefundMetadata;
use PHPUnit\Framework\TestCase;

class RefundMetadataTest extends TestCase
{
    /**
     * This test verifies that its possible to assign the
     * correct type for the metadata.
     *
     * @return void
     */
    public function testType()
    {
        $metadata = new RefundMetadata('abc', []);

        $this->assertEquals('abc', $metadata->getType());
    }

    /**
     * This test verifies that its possible to assign composition items
     * and that they are stored.
     *
     * @return void
     */
    public function testCompositionItems()
    {
        $items = [];
        $items[] = new RefundItem('mol1', 'art-123', 2, 9.99, 'sw1', 'swVersion1');
        $items[] = new RefundItem('mol1', 'art-123', 2, 9.99, 'sw1', 'swVersion1');

        $metadata = new RefundMetadata('abc', $items);

        $this->assertCount(2, $metadata->getComposition());
    }
}
