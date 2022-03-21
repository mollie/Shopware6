<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Compatibility\Bundles\FlowBuilder\Actions;


use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Actions\RefundOrderAction;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Actions\ShipOrderAction;
use MolliePayments\Tests\Fakes\FakeMollieShipment;
use MolliePayments\Tests\Fakes\FakeOrderService;
use MolliePayments\Tests\Fakes\FakeRefundService;
use MolliePayments\Tests\Traits\FlowBuilderTestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\OrderEntity;

class RefundOrderActionTest extends TestCase
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
        $this->assertEquals('action.mollie.order.refund', RefundOrderAction::getName());
    }

    /**
     * This test verifies that our refund is correctly triggered
     * when this flow action is started.
     * We get our order and number from a fake service and send it to the fake refund.
     * If everything works out correctly, our refund service is called with a full-refund
     * as well as the correct order.
     *
     * @return void
     * @throws \Exception
     */
    public function testRefundAction()
    {
        $order = new OrderEntity();
        $order->setId('O1');
        $order->setOrderNumber('ord-123');

        $fakeOrderService = new FakeOrderService($order);
        $fakeRefund = new FakeRefundService('r123', 0);

        $flowEvent = $this->buildOrderStateFlowEvent($order, 'action.mollie.order.refund');

        # build our action and
        # start the handling process with our prepared data
        $action = new RefundOrderAction($fakeOrderService, $fakeRefund, new NullLogger());
        $action->handle($flowEvent);

        # let's see if our refund service did receive
        # the correct calls and data
        $this->assertEquals(true, $fakeRefund->isFullyRefunded());
        $this->assertEquals('ord-123', $fakeRefund->getRefundedOrder()->getOrderNumber());
    }

}