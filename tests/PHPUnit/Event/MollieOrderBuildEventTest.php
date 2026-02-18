<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Event;

use Kiener\MolliePayments\Event\MollieOrderBuildEvent;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MollieOrderBuildEventTest extends TestCase
{
    /**
     * This function tests that its possible to assign
     * metadata correctly to our event.
     * This can be used by 3rd party developers, and the core system of the
     * Mollie plugin will then use that metadata when building the orders.
     */
    public function testMetadata(): void
    {
        $fakeSalesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

        $event = new MollieOrderBuildEvent(
            [],
            new OrderEntity(),
            'tr-123',
            $fakeSalesChannelContext
        );

        // default should be an empty array
        // and not NULL
        $this->assertEquals([], $event->getMetadata());

        // assign our custom metadata
        // to our event
        $event->setMetadata(
            [
                'phpunit' => true,
            ]
        );

        $expected = [
            'phpunit' => true,
        ];

        $this->assertEquals($expected, $event->getMetadata());
    }
}
