<?php

namespace MolliePayments\Tests\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\MollieApi\Order as MollieOrderApi;
use Kiener\MolliePayments\Service\MollieApi\Payment as MolliePaymentApi;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\OrderLine;
use Mollie\Api\Resources\OrderLineCollection;
use Mollie\Api\Types\OrderLineType;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Context;
use Symfony\Component\Routing\RouterInterface;

class OrderTest extends TestCase
{
    /**
     * @var MollieApiClient
     */
    private $clientMock;

    /**
     * @var LoggerInterface
     */
    private $loggerServiceMock;

    /**
     * @var MollieOrderApi
     */
    private $orderApiService;

    /**
     * @var MolliePaymentApi
     */
    private $paymentApiService;

    /**
     * @var RouterInterface
     */
    protected $router;


    /***
     * @return void
     */
    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(MollieApiClient::class);

        $apiFactoryMock = $this->createConfiguredMock(
            MollieApiFactory::class,
            ['createClient' => $this->clientMock, 'getClient' => $this->clientMock]
        );

        $this->loggerServiceMock = new NullLogger();
        $this->paymentApiService = new MolliePaymentApi($apiFactoryMock);
        $this->router = $this->getMockBuilder(RouterInterface::class)->disableOriginalConstructor()->getMock();

        $this->orderApiService = new MollieOrderApi(
            $apiFactoryMock,
            $this->paymentApiService,
            $this->router,
            $this->loggerServiceMock
        );
    }

    /**
     * Tests if an order is being returned if it exists in Mollie
     */
    public function testGetMollieOrder()
    {
        $mollieOrder = $this->createMock(Order::class);
        $orderEndpoint = $this->createConfiguredMock(OrderEndpoint::class, [
            'get' => $mollieOrder
        ]);

        $orderEndpoint->expects($this->once())->method('get');

        $this->clientMock->orders = $orderEndpoint;

        $order = $this->orderApiService->getMollieOrder('foo', 'bar');

        $this->assertSame($mollieOrder, $order);
    }

    /**
     * Tests if an exception is thrown when Mollie throws an ApiException because the order does not exist
     */
    public function testGetMollieOrderThrowsExceptionIfNotExists()
    {
        $orderEndpoint = $this->createMock(OrderEndpoint::class);
        $orderEndpoint->method('get')->willThrowException(new ApiException());

        $orderEndpoint->expects($this->once())->method('get');

        $this->clientMock->orders = $orderEndpoint;

        $this->expectException(CouldNotFetchMollieOrderException::class);

        $this->orderApiService->getMollieOrder('foo', 'bar');
    }

    /**
     * @param string $type
     * @param int $shippableQuantity
     * @param bool $expectedValue
     * @dataProvider getIsCompletelyShippedData
     */
    public function testIsCompletelyShipped(string $type, int $shippableQuantity, bool $expectedValue)
    {
        $mollieOrderLine = $this->createMock(OrderLine::class);
        $mollieOrderLine->type = $type;
        $mollieOrderLine->shippableQuantity = $shippableQuantity;

        $mollieOrderLineCollection = new OrderLineCollection(1, []);
        $mollieOrderLineCollection->append($mollieOrderLine);

        $mollieOrder = $this->createConfiguredMock(Order::class, [
            'lines' => $mollieOrderLineCollection
        ]);

        $orderEndpoint = $this->createConfiguredMock(OrderEndpoint::class, [
            'get' => $mollieOrder
        ]);

        $orderEndpoint->expects($this->once())->method('get');

        $this->clientMock->orders = $orderEndpoint;

        $actualValue = $this->orderApiService->isCompletelyShipped('foo', 'bar');

        $this->assertIsBool($actualValue);
        $this->assertEquals($expectedValue, $actualValue);
    }

    public function getIsCompletelyShippedData()
    {
        return [
            // These types are available as line items in Shopware, so test whether they need to be shipped.
            [OrderLineType::TYPE_PHYSICAL, 0, true],
            [OrderLineType::TYPE_PHYSICAL, 1, false],
            [OrderLineType::TYPE_DIGITAL, 0, true],
            [OrderLineType::TYPE_DIGITAL, 1, false],
            [OrderLineType::TYPE_DISCOUNT, 0, true],
            [OrderLineType::TYPE_DISCOUNT, 1, false],
            [OrderLineType::TYPE_STORE_CREDIT, 0, true],
            [OrderLineType::TYPE_STORE_CREDIT, 1, false],

            // These two types are not (yet) being used by the Mollie plugin, so there should not be any order lines
            // with these types in the Mollie order, and we cannot ship them using Facade/MollieShipment::shipItem.
            // Therefore we mark the (Shopware) order completely shipped.
            [OrderLineType::TYPE_GIFT_CARD, 0, true],
            [OrderLineType::TYPE_GIFT_CARD, 1, true],
            [OrderLineType::TYPE_SURCHARGE, 0, true],
            [OrderLineType::TYPE_SURCHARGE, 1, true],

            // Shipping Fee is not a line item in Shopware, so it cannot be shipped using Facade/MollieShipmen::shipItem.
            // Therefore we mark the (Shopware) order completely shipped.
            [OrderLineType::TYPE_SHIPPING_FEE, 0, true],
            [OrderLineType::TYPE_SHIPPING_FEE, 1, true],
        ];
    }
}
