<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Service\MollieApi;

use Kiener\MolliePayments\Exception\MollieOrderCancelledException;
use Kiener\MolliePayments\Exception\MollieOrderExpiredException;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Handler\PaymentHandler;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\MollieApi\Order as MollieOrderApi;
use Kiener\MolliePayments\Service\MollieApi\Payment as MolliePaymentApi;
use Kiener\MolliePayments\Service\MollieApi\RequestAnonymizer\MollieRequestAnonymizer;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Endpoints\PaymentEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;
use Mollie\Api\Types\OrderStatus;
use MolliePayments\Tests\Traits\BuilderTestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[CoversClass(MollieOrderApi::class)]
class OrderCreateOrReusePaymentTest extends TestCase
{
    use BuilderTestTrait;

    private MollieApiClient $clientMock;

    private MollieOrderApi $orderApiService;

    private OrderEndpoint $orderEndpoint;

    private SalesChannelContext $salesChannelContext;

    private PaymentHandler $paymentHandler;

    private OrderEntity $order;

    private CustomerEntity $customer;

    protected function setUp(): void
    {
        $this->clientMock = $this->createMock(MollieApiClient::class);

        $apiFactoryMock = $this->createConfiguredMock(
            MollieApiFactory::class,
            ['createClient' => $this->clientMock, 'getClient' => $this->clientMock]
        );

        $paymentApiService = new MolliePaymentApi($apiFactoryMock);

        $this->orderApiService = new MollieOrderApi(
            $apiFactoryMock,
            $paymentApiService,
            $this->buildRoutingBuilder($this, 'https://shop.test/webhook'),
            new MollieRequestAnonymizer('*'),
            new NullLogger(),
            $this->createMock(SettingsService::class),
            $this->createMock(CustomerService::class),
        );

        $this->orderEndpoint = $this->createMock(OrderEndpoint::class);
        $this->clientMock->orders = $this->orderEndpoint;
        $this->clientMock->payments = $this->createMock(PaymentEndpoint::class);

        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId(Uuid::randomHex());

        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->salesChannelContext->method('getSalesChannel')->willReturn($salesChannelEntity);
        $this->salesChannelContext->method('getSalesChannelId')->willReturn($salesChannelEntity->getId());

        $this->paymentHandler = $this->createMock(PaymentHandler::class);
        $this->order = new OrderEntity();
        $this->order->setId(Uuid::randomHex());
        $this->order->setSalesChannelId($salesChannelEntity->getId());
        $this->customer = new CustomerEntity();
        $this->customer->setId(Uuid::randomHex());
    }

    public function testThrowsExceptionWhenOrderIsCancelled(): void
    {
        $mollieOrder = $this->createMock(Order::class);
        $mollieOrder->status = OrderStatus::STATUS_CANCELED;

        $this->orderEndpoint->method('get')->willReturn($mollieOrder);

        $this->expectException(MollieOrderCancelledException::class);

        $this->orderApiService->createOrReusePayment(
            'ord_test',
            'klarnapaylater',
            'swTransaction123',
            $this->paymentHandler,
            $this->order,
            $this->customer,
            $this->salesChannelContext
        );
    }

    public function testThrowsExceptionWhenOrderIsExpired(): void
    {
        $mollieOrder = $this->createMock(Order::class);
        $mollieOrder->status = OrderStatus::STATUS_EXPIRED;

        $this->orderEndpoint->method('get')->willReturn($mollieOrder);

        $this->expectException(MollieOrderExpiredException::class);

        $this->orderApiService->createOrReusePayment(
            'ord_test',
            'klarnapaylater',
            'swTransaction123',
            $this->paymentHandler,
            $this->order,
            $this->customer,
            $this->salesChannelContext
        );
    }

    public function testCancelsOrderAndThrowsWhenOpenPaymentIsNotCancelable(): void
    {
        $openPayment = $this->createMock(Payment::class);
        $openPayment->method('isOpen')->willReturn(true);
        $openPayment->isCancelable = false;
        $openPayment->id = 'tr_open123';

        $paymentCollection = new PaymentCollection($this->clientMock, 1, null);
        $paymentCollection->append($openPayment);

        $mollieOrder = $this->createMock(Order::class);
        $mollieOrder->status = OrderStatus::STATUS_CREATED;
        $mollieOrder->method('payments')->willReturn($paymentCollection);

        $this->orderEndpoint->method('get')->willReturn($mollieOrder);

        $this->expectException(MollieOrderCancelledException::class);

        $this->orderApiService->createOrReusePayment(
            'ord_test',
            'paypal',
            'swTransaction123',
            $this->paymentHandler,
            $this->order,
            $this->customer,
            $this->salesChannelContext
        );
    }
}
