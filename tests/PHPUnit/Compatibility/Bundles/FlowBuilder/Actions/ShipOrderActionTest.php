<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Compatibility\Bundles\FlowBuilder\Actions;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Actions\RefundOrderAction;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Actions\ShipOrderAction;
use Mollie\Api\Resources\Refund;
use Mollie\Api\Types\RefundStatus;
use MolliePayments\Tests\Fakes\FakeMollieShipment;
use MolliePayments\Tests\Fakes\FakeOrderService;
use MolliePayments\Tests\Traits\FlowBuilderTestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Util\Random;

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
     * @return void
     */
    public function testShippingAction()
    {
        $order = new OrderEntity();
        $order->setId('O1');
        $order->setOrderNumber('ord-123');

        $fakeOrderService = new FakeOrderService($order);
        $fakeShipment = new FakeMollieShipment();

        $flowEvent = $this->buildOrderStateFlowEvent($order, 'action.mollie.order.ship');

        # build our action and
        # start the handling process with our prepared data
        $action = new ShipOrderAction($fakeOrderService, $fakeShipment, new NullLogger());
        $action->handle($flowEvent);

        # let's see if our shipment service did receive
        # the correct calls and data
        $this->assertEquals(true, $fakeShipment->isFullyShipped());
        $this->assertEquals('ord-123', $fakeShipment->getShippedOrderNumber());
    }
}
