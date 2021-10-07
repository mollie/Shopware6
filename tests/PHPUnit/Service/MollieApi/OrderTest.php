<?php

namespace MolliePayments\Tests\Service\MollieApi;

use Kiener\MolliePayments\Exception\CouldNotFetchMollieOrderException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\MollieApi\Order as MollieOrderApi;
use Kiener\MolliePayments\Service\MollieApi\Payment as MolliePaymentApi;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

class OrderTest extends TestCase
{
    /**
     * @var MollieApiClient
     */
    private $clientMock;

    /**
     * @var LoggerService
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
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->clientMock = $this->createMock(MollieApiClient::class);

        $apiFactoryMock = $this->createConfiguredMock(
            MollieApiFactory::class,
            ['createClient' => $this->clientMock, 'getClient' => $this->clientMock]
        );

        $this->loggerServiceMock = $this->createMock(LoggerService::class);
        $this->paymentApiService = new MolliePaymentApi($apiFactoryMock);
        $this->orderApiService = new MollieOrderApi($apiFactoryMock, $this->paymentApiService, $this->loggerServiceMock);
    }

    public function testGetMollieOrder()
    {
        $mollieOrder = $this->createMock(Order::class);
        $orderEndpoint = $this->createConfiguredMock(OrderEndpoint::class, [
            'get' => $mollieOrder
        ]);

        $orderEndpoint->expects($this->once())->method('get');

        $this->clientMock->orders = $orderEndpoint;

        $order = $this->orderApiService->getMollieOrder('foo', 'bar', $this->context);

        $this->assertSame($mollieOrder, $order);
    }

    public function testGetMollieOrderThrowsExceptionIfNotExists()
    {
        $orderEndpoint = $this->createMock(OrderEndpoint::class);
        $orderEndpoint->method('get')->willThrowException(new ApiException());

        $orderEndpoint->expects($this->once())->method('get');

        $this->clientMock->orders = $orderEndpoint;

        $this->expectException(CouldNotFetchMollieOrderException::class);

        $this->orderApiService->getMollieOrder('foo', 'bar', $this->context);
    }
}
