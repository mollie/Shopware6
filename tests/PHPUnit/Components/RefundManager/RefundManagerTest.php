<?php


namespace MolliePayments\Tests\Components\RefundManager;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Components\RefundManager\Builder\RefundDataBuilder;
use Kiener\MolliePayments\Components\RefundManager\RefundManager;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItem;
use Kiener\MolliePayments\Repository\Refund\RefundRepositoryInterface;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order as MollieOrder;
use MolliePayments\Tests\Fakes\FakeOrderService;
use MolliePayments\Tests\Fakes\FakeRefundService;
use MolliePayments\Tests\Fakes\FlowBuilder\FakeFlowBuilderDispatcher;
use MolliePayments\Tests\Fakes\FlowBuilder\FakeFlowBuilderFactory;
use MolliePayments\Tests\Fakes\StockUpdater\FakeStockManager;
use MolliePayments\Tests\Traits\MockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

class RefundManagerTest extends TestCase
{

    use MockTrait;


    /**
     * @var RefundManager
     */
    private $manager;

    /**
     * @var FakeFlowBuilderDispatcher
     */
    private $fakeFlowBuilderDispatcher;

    /**
     * @var FakeStockManager
     */
    private $fakeStockUpdater;


    /**
     * @return void
     * @throws \Exception
     */
    protected function setUp(): void
    {
        parent::setUp();


        $order = new OrderEntity();
        $order->setId('O1');
        $order->setOrderNumber('ord-123');

        $fakeOrderService = new FakeOrderService($order);
        $fakeRefundService = new FakeRefundService('r-xyz-123', 9999);
        $this->fakeStockUpdater = new FakeStockManager();

        /** @var Order|MockObject $fakeOrder */
        $fakeOrder = $this->createDummyMock(Order::class, $this);
        $fakeOrder->method('getMollieOrder')->willReturn(new MollieOrder($this->createMock(MollieApiClient::class)) );


        $this->fakeFlowBuilderDispatcher = new FakeFlowBuilderDispatcher();
        $flowBuilderEventFactory = new FlowBuilderEventFactory('6.4.8.0'); # use any higher version so that we get real events


        $this->manager = new RefundManager(
            new RefundDataBuilder($fakeOrderService, $fakeRefundService, $fakeOrder),
            $fakeOrderService,
            $fakeRefundService,
            $fakeOrder,
            new FakeFlowBuilderFactory($this->fakeFlowBuilderDispatcher),
            $flowBuilderEventFactory,
            $this->fakeStockUpdater,
            $this->createMock(RefundRepositoryInterface::class),
            new NullLogger()
        );
    }

    /**
     * This test verifies that our correct flow builder
     * event is fired with all required data.
     *
     * @return void
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function testFlowBuilderDispatching()
    {
        $order = new OrderEntity();
        $order->setId('O1');
        $order->setOrderNumber('ord-123');
        $order->setSalesChannelId('SC1');
        $order->setAmountTotal(9999);

        /** @var Context $fakeContext */
        $fakeContext = $this->createDummyMock(Context::class, $this);


        $refundRequest = new RefundRequest('', '', '',null);

        $refund = $this->manager->refund($order, $refundRequest, $fakeContext);

        /** @var RefundStartedEvent $firedEvent */
        $firedEvent = $this->fakeFlowBuilderDispatcher->getDispatchedEvent();


        # assert that our correct event was fired
        $this->assertEquals(RefundStartedEvent::class, get_class($firedEvent));
        $this->assertEquals('mollie.refund.started', $firedEvent->getName());
        # now also check for the values
        $this->assertEquals('O1', $firedEvent->getOrderId());
        $this->assertEquals(9999, $firedEvent->getAmount());
    }

    /**
     * This test verifies that our stock service is called
     * with the correct data.
     * We provide a line item id. The code will then search in the
     * order line item entities for that ID and extract the product ID.
     * This will be passed on with the quantity for the stock reset.
     *
     * @return void
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function testStockReset()
    {
        $order = new OrderEntity();
        $order->setId('O1');
        $order->setOrderNumber('ord-123');
        $order->setSalesChannelId('SC1');

        $item1 = new OrderLineItemEntity();
        $item1->setLabel('Product T-Shirt');
        $item1->setId('line-1');
        $item1->setUnitPrice(19.99);
        $item1->setReferencedId('product-id-1');


        $order->setLineItems(new OrderLineItemCollection([$item1]));

        /** @var Context $fakeContext */
        $fakeContext = $this->createDummyMock(Context::class, $this);

        # build a request object
        # so that we refund line-1 and make sure the stock is reset
        $refundRequest = new RefundRequest('', '', '',null);
        $refundRequest->addItem(new RefundRequestItem('line-1', 19.99, 1, 1));

        $refund = $this->manager->refund($order, $refundRequest, $fakeContext);


        # first verify if it was called
        $this->assertEquals(true, $this->fakeStockUpdater->isCalled(), 'Stock Updater was not called');
        # and now verify the passed data
        $this->assertEquals('Product T-Shirt', $this->fakeStockUpdater->getLineItemLabel());
        $this->assertEquals('product-id-1', $this->fakeStockUpdater->getProductID());
        $this->assertEquals(1, $this->fakeStockUpdater->getQuantity());
        $this->assertEquals('r-xyz-123', $this->fakeStockUpdater->getMollieRefundID());
    }

}
