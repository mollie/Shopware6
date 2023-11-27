<?php

namespace MolliePayments\Tests\Service\Refund\Mollie;

use Exception;
use Kiener\MolliePayments\Service\Order\OrderStateService;
use Kiener\MolliePayments\Service\Refund\Item\RefundItem;
use Kiener\MolliePayments\Service\Refund\Mollie\RefundMetadata;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use MolliePayments\Tests\Fakes\FakeEntityRepository;
use MolliePayments\Tests\Fakes\FakeOrderTransitionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\LogEntryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

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
        $items[] = new RefundItem( 'mol1', 'art-123', 2, 9.99,'sw1','swVersion1');
        $items[] = new RefundItem('mol1', 'art-123', 2, 9.99,'sw1','swVersion1');

        $metadata = new RefundMetadata('abc', $items);

        $this->assertCount(2, $metadata->getComposition());
    }



}
