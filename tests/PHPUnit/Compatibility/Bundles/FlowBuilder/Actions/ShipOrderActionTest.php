<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Compatibility\Bundles\FlowBuilder\Actions;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Actions\ShipOrderAction;
use MolliePayments\Shopware\Tests\Fakes\FakeOrderService;
use MolliePayments\Shopware\Tests\Fakes\FakeShipmentManager;
use MolliePayments\Shopware\Tests\Traits\FlowBuilderTestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Event\FlowEvent;

class ShipOrderActionTest extends TestCase
{
    use FlowBuilderTestTrait;

    /**
     * This test verifies that our action name is not
     * touched without recognizing it.
     *
     * @return void
     */
    public function testName()
    {
        $this->assertEquals('action.mollie.order.ship', ShipOrderAction::getName());
    }

    /**
     * This test verifies that our shipment is correctly triggered
     * when this flow action is started.
     * We get our order and number from a fake service and send it to the fake shipment.
     * If everything works out correctly, our shipment service is called with a full-shipment
     * as well as the correct order number.
     *
     * @throws \Exception
     *
     * @return void
     */
    public function testShippingAction()
    {
        $order = new OrderEntity();
        $order->setId('O1');
        $order->setOrderNumber('ord-123');

        $fakeOrderService = new FakeOrderService($order);
        $fakeShipment = new FakeShipmentManager();

        $flowEvent = $this->buildOrderStateFlowEvent($order, 'action.mollie.order.ship');

        // build our action and
        // start the handling process with our prepared data
        $action = new ShipOrderAction($fakeOrderService, $fakeShipment, new NullLogger());
        if (class_exists(FlowEvent::class) && $flowEvent instanceof FlowEvent) {
            $action->handle($flowEvent);
        } else {
            $action->handleFlow($flowEvent);
        }

        // let's see if our shipment service did receive
        // the correct calls and data
        $this->assertEquals(true, $fakeShipment->isFullyShipped());
        $this->assertEquals('ord-123', $fakeShipment->getShippedOrderNumber());
    }
}
