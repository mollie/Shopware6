<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Facade;


use Kiener\MolliePayments\Exception\CouldNotSetRefundAtMollieException;
use Kiener\MolliePayments\Facade\SetMollieOrderRefunded;
use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\TransactionService;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\MollieApiClient;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class SetMollieOrderRefundedTest extends TestCase
{
    /**
     * @var TransactionService|\PHPUnit\Framework\MockObject\MockObject
     */
    private $transactionService;
    /**
     * @var Order|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mollieOrderService;
    /**
     * @var MollieApiFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $apiFactory;
    /**
     * @var SetMollieOrderRefunded
     */
    private $setMollieOrderService;
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|Context
     */
    private $context;

    public function setUp(): void
    {
        $this->transactionService = $this->getMockBuilder(TransactionService::class)->disableOriginalConstructor()->getMock();
        $this->mollieOrderService = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $this->apiFactory = $this->getMockBuilder(MollieApiFactory::class)->disableOriginalConstructor()->getMock();
        $this->setMollieOrderService = new SetMollieOrderRefunded(
            $this->transactionService,
            $this->mollieOrderService,
            $this->apiFactory
        );
        $this->context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
    }

    public function testThrowExceptionIfTransactionCouldNotBeFound(): void
    {
        $this->transactionService->method('getTransactionById')->willReturn(null);

        self::expectException(CouldNotSetRefundAtMollieException::class);
        $this->setMollieOrderService->setRefunded('foo', $this->context);
    }

    public function testThrowExceptionIfTransactionHasNoOrder(): void
    {
        $this->transactionService->method('getTransactionById')->willReturn($this->getTransaction(Uuid::randomHex()));

        self::expectException(CouldNotSetRefundAtMollieException::class);
        $this->setMollieOrderService->setRefunded('foo', $this->context);
    }

    public function testThrowExceptionIfMollieOrderIdCouldNotBeFound(): void
    {
        $order = $this->getOrder(Uuid::randomHex(), Uuid::randomHex());
        $this->transactionService->method('getTransactionById')->willReturn($this->getTransaction(Uuid::randomHex(), $order));

        self::expectException(CouldNotSetRefundAtMollieException::class);
        $this->setMollieOrderService->setRefunded('foo', $this->context);
    }

    public function testThatApiClientIsConstructedWithCorrectSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $mollieOrderId = 'foo';
        $order = $this->getOrder(Uuid::randomHex(), $salesChannelId, $this->getCustomFields($mollieOrderId));
        $this->transactionService->method('getTransactionById')->willReturn($this->getTransaction(Uuid::randomHex(), $order));
        $apiClient = $this->getMockBuilder(MollieApiClient::class)->disableOriginalConstructor()->getMock();
        $orderEndpoint = $this->getMockBuilder(OrderEndpoint::class)->disableOriginalConstructor()->getMock();
        $mollieOrder = $this->getMockBuilder(\Mollie\Api\Resources\Order::class)->disableOriginalConstructor()->getMock();
        $orderEndpoint->method('get')->with($mollieOrderId)->willReturn($mollieOrder);
        $apiClient->orders = $orderEndpoint;

        $this->apiFactory->expects($this->once())->method('getClient')->with($salesChannelId, $this->context)->willReturn($apiClient);
        $this->setMollieOrderService->setRefunded('foo', $this->context);
    }

    public function testThatExceptionIsThrownIfMollieOrderCouldNotBeRetrieved(): void
    {
        $salesChannelId = Uuid::randomHex();
        $mollieOrderId = 'foo';
        $order = $this->getOrder(Uuid::randomHex(), $salesChannelId, $this->getCustomFields($mollieOrderId));
        $this->transactionService->method('getTransactionById')->willReturn($this->getTransaction(Uuid::randomHex(), $order));
        $apiClient = $this->getMockBuilder(MollieApiClient::class)->disableOriginalConstructor()->getMock();
        $orderEndpoint = $this->getMockBuilder(OrderEndpoint::class)->disableOriginalConstructor()->getMock();
        $orderEndpoint->expects($this->once())->method('get')->willThrowException(new ApiException());
        $apiClient->orders = $orderEndpoint;

        $this->apiFactory->expects($this->once())->method('getClient')->with($salesChannelId, $this->context)->willReturn($apiClient);

        self::expectException(CouldNotSetRefundAtMollieException::class);
        $this->setMollieOrderService->setRefunded('foo', $this->context);
    }

    public function testThatRefundIsDone(): void
    {
        $salesChannelId = Uuid::randomHex();
        $mollieOrderId = 'foo';
        $order = $this->getOrder(Uuid::randomHex(), $salesChannelId, $this->getCustomFields($mollieOrderId));
        $this->transactionService->method('getTransactionById')->willReturn($this->getTransaction(Uuid::randomHex(), $order));
        $apiClient = $this->getMockBuilder(MollieApiClient::class)->disableOriginalConstructor()->getMock();
        $orderEndpoint = $this->getMockBuilder(OrderEndpoint::class)->disableOriginalConstructor()->getMock();
        $mollieOrder = $this->getMockBuilder(\Mollie\Api\Resources\Order::class)->disableOriginalConstructor()->getMock();

        $orderEndpoint->method('get')->with($mollieOrderId)->willReturn($mollieOrder);
        $apiClient->orders = $orderEndpoint;

        $this->apiFactory->expects($this->once())->method('getClient')->with($salesChannelId, $this->context)->willReturn($apiClient);

        $mollieOrder->expects($this->once())->method('refundAll');

        $this->setMollieOrderService->setRefunded('foo', $this->context);
    }


    private function getTransaction(string $transactionId, ?OrderEntity $order = null): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId($transactionId);

        if ($order instanceof OrderEntity) {
            $transaction->setOrder($order);
        }

        return $transaction;
    }

    private function getOrder(string $orderId, string $salesChannelId, array $customFields = []): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setSalesChannelId($salesChannelId);

        if (!empty($customFields)) {
            $order->setCustomFields($customFields);
        }

        return $order;
    }

    private function getCustomFields(string $mollieOrderId): array
    {
        return [
            'mollie_payments' => [
                'order_id' => $mollieOrderId
            ]
        ];
    }
}
