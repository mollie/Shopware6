<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Compatibility\Bundles\FlowBuilder\Actions;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Actions\RefundOrderAction;
use MolliePayments\Tests\Fakes\FakeOrderService;
use MolliePayments\Tests\Fakes\FakeRefundManager;
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
     * This test verifies that our refund is correctly triggered when this flow action is started.
     * We get our order and number from a fake service and send it to the fake refund manager.
     * If everything works out correctly, our refund service is called with a full-refund
     * as well as the correct order. So we also verify that a full refund request is built
     *
     * @throws \Exception
     * @return void
     */
    public function testRefundAction()
    {
        $order = new OrderEntity();
        $order->setId('O1');
        $order->setOrderNumber('ord-123');
        $order->setAmountTotal(19.99);

        $fakeOrderService = new FakeOrderService($order);
        $fakeRefundManager = new FakeRefundManager('r123', 0);

        $flowEvent = $this->buildOrderStateFlowEvent($order, 'action.mollie.order.refund');

        # build our action and
        # start the handling process with our prepared data
        $action = new RefundOrderAction($fakeOrderService, $fakeRefundManager, new NullLogger());
        $action->handle($flowEvent);


        # verify the passed request object
        $this->assertEquals('ord-123', $fakeRefundManager->getRefundRequest()->getOrderNumber());
        $this->assertEquals('Refund through Shopware Flow Builder', $fakeRefundManager->getRefundRequest()->getDescription());
        $this->assertEquals(null, $fakeRefundManager->getRefundRequest()->getAmount(), 'amount needs to be NULL to detect it as full refund');
        $this->assertEquals([], $fakeRefundManager->getRefundRequest()->getItems());

        # verify that the correct order has been fetched
        $this->assertEquals('ord-123', $fakeRefundManager->getRefundedOrder()->getOrderNumber());
    }
}
