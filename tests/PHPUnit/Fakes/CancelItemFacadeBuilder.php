<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Components\CancelManager\CancelItemFacade;
use Kiener\MolliePayments\Components\RefundManager\Integrators\StockManagerInterface;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\OrderLineCollection;
use MolliePayments\Tests\Fakes\Repositories\FakeOrderLineItemRepository;
use MolliePayments\Tests\Fakes\StockUpdater\FakeStockManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @final
 */
class CancelItemFacadeBuilder
{
    /** @var MollieApiClient */
    private $mollieClient;

    /** @var TestCase */
    private $testCase;

    private OrderLineItemCollection $itemCollection;

    private StockManagerInterface $stockManager;

    public function __construct(TestCase $testCase)
    {
        $this->testCase = $testCase;

        $this->mollieClient = $testCase->getMockBuilder(MollieApiClient::class)->disableOriginalConstructor()->getMock();
        $this->itemCollection = new OrderLineItemCollection();
        $this->stockManager = new FakeStockManager();
    }

    public function withInvalidOrder(): self
    {
        $mockOrderEndpoint = $this->testCase->getMockBuilder(OrderEndpoint::class)->disableOriginalConstructor()->getMock();
        $mockOrderEndpoint->method('get')->willThrowException(new ApiException('Invalid order'));

        $this->mollieClient->orders = $mockOrderEndpoint;

        return $this;
    }

    public function withDefaultOrder(): self
    {
        $mockOrderLine = $this->testCase->getMockBuilder(OrderLine::class)->disableOriginalConstructor()->getMock();
        $mockOrderLine->cancelableQuantity = 2;
        $mockOrderLine->id = 'valid';

        $oderLineCollection = new OrderLineCollection(1, null);
        $oderLineCollection[0] = $mockOrderLine;

        $mockOrder = $this->testCase->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $mockOrder->method('lines')->willReturn($oderLineCollection);

        $mockOrderEndpoint = $this->testCase->getMockBuilder(OrderEndpoint::class)->disableOriginalConstructor()->getMock();
        $mockOrderEndpoint->method('get')->willReturn($mockOrder);

        $this->mollieClient->orders = $mockOrderEndpoint;

        return $this;
    }

    public function withValidOrderLine(): self
    {
        $fakeShopwareOrderLine = new OrderLineItemEntity();
        $fakeShopwareOrderLine->setId('validLineId');
        $fakeShopwareOrderLine->setLabel('Valid orderline');

        $fakeShopwareOrder = new OrderEntity();
        $fakeShopwareOrder->setId('validOrderId');
        $fakeShopwareOrder->setSalesChannelId('testSalesChannelId');
        $fakeShopwareOrderLine->setOrder($fakeShopwareOrder);
        $this->itemCollection->add($fakeShopwareOrderLine);

        return $this;
    }

    public function getStockManager(): FakeStockManager
    {
        return $this->stockManager;
    }

    public function bild(): CancelItemFacade
    {
        /** @var MollieApiFactory $mollieFactory */
        $mollieFactory = $this->testCase->getMockBuilder(MollieApiFactory::class)->disableOriginalConstructor()->getMock();
        $mollieFactory->method('getClient')->willReturn($this->mollieClient);
        $dispatcher = $this->testCase->getMockBuilder(EventDispatcherInterface::class)->getMock();
        $orderLineRepository = new FakeOrderLineItemRepository($this->itemCollection);

        return new CancelItemFacade($mollieFactory, $orderLineRepository, $this->stockManager, $dispatcher, new NullLogger());
    }
}
