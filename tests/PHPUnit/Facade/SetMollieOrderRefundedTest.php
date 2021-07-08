<?php declare(strict_types=1);

namespace MolliePayments\Tests\Facade;

use Kiener\MolliePayments\Exception\CouldNotSetRefundAtMollieException;
use Kiener\MolliePayments\Facade\SetMollieOrderRefunded;
use Kiener\MolliePayments\Service\RefundService;
use Kiener\MolliePayments\Service\TransactionService;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;
use Mollie\Api\Types\PaymentStatus;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SetMollieOrderRefundedTest extends TestCase
{
    /**
     * @var TransactionService|\PHPUnit\Framework\MockObject\MockObject
     */
    private $transactionService;

    /**
     * @var RefundService|\PHPUnit\Framework\MockObject\MockObject
     */
    private $refundService;

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
        $this->refundService = $this->getMockBuilder(RefundService::class)->disableOriginalConstructor()->getMock();
        $this->setMollieOrderService = new SetMollieOrderRefunded(
            $this->transactionService,
            $this->refundService
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

    /**
     * Test whether the setMollieOrderRefunded facade creates a refund at Mollie, with the given total and refunded amounts
     * When refunded is higher than or equal to, it should not create a refund.
     *
     * @param float $amountTotal
     * @param float $amountRefunded
     * @param float|null $expectedRefund
     * @dataProvider getRefundsTestData
     */
    public function testThatRefundIsDone(
        float $amountTotal,
        float $amountRefunded,
        ?float $expectedRefund
    ): void
    {
        $salesChannelId = Uuid::randomHex();
        $mollieOrderId = 'foo';
        $order = $this->getOrder(Uuid::randomHex(), $salesChannelId, $amountTotal, $this->getCustomFields($mollieOrderId));

        $this->transactionService->method('getTransactionById')->willReturn($this->getTransaction(Uuid::randomHex(), $order));

        $apiClient = $this->getMockBuilder(MollieApiClient::class)->disableOriginalConstructor()->getMock();
        $orderEndpoint = $this->getMockBuilder(OrderEndpoint::class)->disableOriginalConstructor()->getMock();
        $mollieOrder = $this->getMockBuilder(\Mollie\Api\Resources\Order::class)->disableOriginalConstructor()->getMock();

        $paymentMock = $this->createConfiguredMock(
            Payment::class,
            ['getAmountRefunded' => $amountRefunded]
        );
        $paymentMock->status = PaymentStatus::STATUS_PAID;

        $paymentCollectionMock = $this->createConfiguredMock(
            PaymentCollection::class,
            ['getArrayCopy' => [$paymentMock]]
        );

        $mollieOrder->method('payments')->willReturn($paymentCollectionMock);

        $orderEndpoint->method('get')->with($mollieOrderId)->willReturn($mollieOrder);
        $apiClient->orders = $orderEndpoint;

        $this->refundService->expects($this->once())->method('getRefundedAmount')->with($order)->willReturn($amountRefunded);

        if (is_null($expectedRefund)) {
            $this->refundService->expects($this->never())->method('refund');
        } else {
            $this->refundService->expects($this->once())->method('refund')->with($order, $expectedRefund);
        }

        $this->setMollieOrderService->setRefunded('foo', $this->context);
    }

    public function getRefundsTestData(): array
    {
        return [
            "Do refund: Total 99.99, refunded 0 => 99.99" => [
                99.99,
                0,
                99.99
            ],
            "Do refund: Total 100, refunded 12.34 => 87.66" => [
                100,
                12.34,
                87.66
            ],
            "Do refund: Total 255, refunded 123.45 => 131.55" => [
                255,
                123.45,
                131.55
            ],
            "Do refund: Total 437, refunded 112 => 325" => [
                437,
                112,
                325
            ],
            "Do refund: Total 452.64, refunded 143.84 => 308.80" => [
                452.64,
                143.84,
                308.80
            ],
            "Do refund: Total 845.23, refunded 356.77 => 488.46" => [
                845.23,
                356.77,
                488.46
            ],
            "Don't refund: Total 124.99, refunded 149.99 => no refund" => [
                124.99,
                149.99,
                null
            ],
            "Don't refund: Total 100, refunded 100 => no refund" => [
                100,
                100,
                null
            ],
            "Do refund: Total 100, refunded 99.99 => 0.01" => [
                100,
                99.99,
                0.01
            ],
        ];
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

    private function getOrder(
        string $orderId,
        string $salesChannelId,
        float $amountTotal = 0,
        array $customFields = []
    ): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setSalesChannelId($salesChannelId);
        $order->setAmountTotal($amountTotal);

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
