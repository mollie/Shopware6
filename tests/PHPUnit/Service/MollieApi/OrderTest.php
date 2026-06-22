<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Order as MollieOrderApi;
use Kiener\MolliePayments\Service\MollieApi\Payment as MolliePaymentApi;
use Kiener\MolliePayments\Service\MollieApi\RequestAnonymizer\MollieRequestAnonymizer;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use MolliePayments\Shopware\Tests\Traits\BuilderTestTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\RouterInterface;

class OrderTest extends TestCase
{
    use BuilderTestTrait;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var MollieApiClient
     */
    private $clientMock;

    /**
     * @var MollieOrderApi
     */
    private $orderApiService;

    /**
     * @var MolliePaymentApi
     */
    private $paymentApiService;

    /*
     * @return void
     */
    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(MollieApiClient::class);

        $apiFactoryMock = $this->createConfiguredMock(
            MollieApiFactory::class,
            ['createClient' => $this->clientMock, 'getClient' => $this->clientMock]
        );

        $this->paymentApiService = new MolliePaymentApi($apiFactoryMock);
        $this->router = $this->getMockBuilder(RouterInterface::class)->disableOriginalConstructor()->getMock();

        $this->orderApiService = new MollieOrderApi(
            $apiFactoryMock,
            $this->paymentApiService,
            $this->buildRoutingBuilder($this, ''),
            new MollieRequestAnonymizer('*'),
            new NullLogger(),
            $this->createMock(SettingsService::class),
            $this->createMock(CustomerService::class),
        );
    }

    /**
     * Tests if an order is being returned if it exists in Mollie
     */
    public function testGetMollieOrder()
    {
        $mollieOrder = $this->createMock(Order::class);
        $orderEndpoint = $this->createConfiguredMock(OrderEndpoint::class, [
            'get' => $mollieOrder,
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
}
