<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Components\RefundManager;

use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\Events\Refund\RefundStarted\RefundStartedEvent;
use Kiener\MolliePayments\Compatibility\Bundles\FlowBuilder\FlowBuilderEventFactory;
use Kiener\MolliePayments\Compatibility\VersionCompare;
use Kiener\MolliePayments\Components\RefundManager\Builder\RefundDataBuilder;
use Kiener\MolliePayments\Components\RefundManager\RefundManager;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequest;
use Kiener\MolliePayments\Components\RefundManager\Request\RefundRequestItem;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\Refund\RefundCreditNoteService;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order as MollieOrder;
use MolliePayments\Tests\Fakes\FakeOrderService;
use MolliePayments\Tests\Fakes\FakeRefundService;
use MolliePayments\Tests\Fakes\FlowBuilder\FakeFlowBuilderDispatcher;
use MolliePayments\Tests\Fakes\FlowBuilder\FakeFlowBuilderFactory;
use MolliePayments\Tests\Fakes\Repositories\FakeRefundRepository;
use MolliePayments\Tests\Fakes\StockUpdater\FakeStockManager;
use MolliePayments\Tests\Traits\MockTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

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
     * @var FakeRefundRepository
     */
    private $fakeRefundRespository;

    /**
     * @var Context
     */
    private $fakeContext;

    /**
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

        /** @var MockObject|Order $fakeOrder */
        $fakeOrder = $this->createDummyMock(Order::class, $this);
        $fakeOrder->method('getMollieOrder')->willReturn(new MollieOrder($this->createMock(MollieApiClient::class)));

        $fakeSalesChannelContextFactory = $this->createMock(SalesChannelContextFactory::class);

        $this->fakeFlowBuilderDispatcher = new FakeFlowBuilderDispatcher();
        $flowBuilderEventFactory = new FlowBuilderEventFactory(new VersionCompare('6.4.8.0'), $fakeSalesChannelContextFactory); // use any higher version so that we get real events

        $this->fakeRefundRespository = new FakeRefundRepository();
        $fakeRefundCreditNotesService = $this->createMock(RefundCreditNoteService::class);
        $this->manager = new RefundManager(
            new RefundDataBuilder($fakeOrderService, $fakeRefundService, $fakeOrder, new NullLogger()),
            $fakeOrderService,
            $fakeRefundService,
            $fakeOrder,
            new FakeFlowBuilderFactory($this->fakeFlowBuilderDispatcher),
            $flowBuilderEventFactory,
            $this->fakeStockUpdater,
            $this->fakeRefundRespository,
            $fakeRefundCreditNotesService,
            new NullLogger()
        );

        $this->fakeContext = $this->createDummyMock(Context::class, $this);
    }

    /**
     * This test verifies that our correct flow builder
     * event is fired with all required data.
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     *
     * @return void
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

        $refundRequest = new RefundRequest('', '', '', null);

        $refund = $this->manager->refund($order, $refundRequest, $fakeContext);

        /** @var RefundStartedEvent $firedEvent */
        $firedEvent = $this->fakeFlowBuilderDispatcher->getDispatchedEvent();

        // assert that our correct event was fired
        $this->assertEquals(RefundStartedEvent::class, get_class($firedEvent));
        $this->assertEquals('mollie.refund.started', $firedEvent->getName());
        // now also check for the values
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
     * @throws \Mollie\Api\Exceptions\ApiException
     *
     * @return void
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

        // build a request object
        // so that we refund line-1 and make sure the stock is reset
        $refundRequest = new RefundRequest('', '', '', null);
        $refundRequest->addItem(new RefundRequestItem('line-1', 19.99, 1, 1));

        $refund = $this->manager->refund($order, $refundRequest, $fakeContext);

        // first verify if it was called
        $this->assertEquals(true, $this->fakeStockUpdater->isCalled(), 'Stock Updater was not called');
        // and now verify the passed data
        $this->assertEquals('Product T-Shirt', $this->fakeStockUpdater->getLineItemLabel());
        $this->assertEquals('product-id-1', $this->fakeStockUpdater->getProductID());
        $this->assertEquals(1, $this->fakeStockUpdater->getQuantity());
    }

    /**
     * This test verifies that we can also refund line items with quantity 0 as long as they have an amount.
     *  This is necessary because sometimes you have already refunded all quantities, but still want to add another amount value.
     *  In this case, you also want a composition-reference to the line item, which requires a qty of 0 to work.
     *  The test will start a request with a quantity of 0 and a valid price, and we verify that the DAL create function gets a
     *  correct payload, including the refund item.
     *
     * @testWith [ 0, 19.99 ]
     *            [ 1, 0 ]
     *            [ 0, -5 ]
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function testValidItemsAreAdded(int $qty, float $itemPrice): void
    {
        $order = $this->buildValidOrder();

        $refundRequest = new RefundRequest('', '', '', 2);
        $refundRequest->addItem(new RefundRequestItem('line-1', $itemPrice, $qty, 0));

        $this->manager->refund($order, $refundRequest, $this->fakeContext);

        $dalCreateData = $this->fakeRefundRespository->getReceivedCreateData();

        $expectedItems = [
            [
                'mollieLineId' => 'odl_123',
                'label' => 'product-id-1',
                'quantity' => max($qty, 1),
                'amount' => $itemPrice,
                'orderLineItemId' => 'line-1',
                'orderLineItemVersionId' => null,
            ],
        ];

        $this->assertEquals($expectedItems, $dalCreateData[0]['refundItems'], 'Make sure that valid items are being added to the DAL payload.');
    }

    /**
     * This test verifies that invalid items, even though they are sent with the request, are NOT being added to the DAL payload aka into the database.
     * We have a strict definition on what is valid or invalid.
     * So we build invalid items and make sure no refundItems are saved into the database.
     *
     * @testWith [ 0, 0 ]
     *           [ -1, 20 ]
     *
     * @throws \Mollie\Api\Exceptions\ApiException
     * @throws \Mollie\Api\Exceptions\ApiException
     *                                             /
     */
    public function testInvalidItemsAreNotAdded(int $qty, float $itemPrice): void
    {
        $order = $this->buildValidOrder();

        $refundRequest = new RefundRequest('', '', '', 2);
        $refundRequest->addItem(new RefundRequestItem('line-1', $itemPrice, $qty, 0));

        $this->manager->refund($order, $refundRequest, $this->fakeContext);

        $dalCreateData = $this->fakeRefundRespository->getReceivedCreateData();

        $this->assertArrayNotHasKey('refundItems', $dalCreateData[0], 'Make sure that invalid items are not added to the DAL payload');
    }

    private function buildValidOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('O1');
        $order->setAmountTotal(19.99);
        $order->setSalesChannelId('SC1');

        $item1 = new OrderLineItemEntity();
        $item1->setId('line-1');
        $item1->setUnitPrice(19.99);
        $item1->setLabel('Product T-Shirt');
        $item1->setReferencedId('product-id-1');

        // required to mark as mollie line item so that its even found
        $item1->setPayload([
            'customFields' => [
                'mollie_payments_product_order_line_id' => 'odl_123',
            ],
        ]);

        $order->setLineItems(new OrderLineItemCollection([$item1]));

        return $order;
    }
}
