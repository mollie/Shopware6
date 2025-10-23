<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Hydrator;

use Kiener\MolliePayments\Hydrator\RefundHydrator;
use Mollie\Api\Resources\Refund;
use Mollie\Api\Types\RefundStatus;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;

class RefundHydratorTest extends TestCase
{
    /**
     * @var RefundHydrator
     */
    private $refundHydrator;

    public function setUp(): void
    {
        $this->refundHydrator = new RefundHydrator();
    }

    /**
     * @dataProvider getHydratorTestData
     */
    public function testRefundHydrator(array $expected, Refund $refund)
    {
        $orderMock = $this->createMock(OrderEntity::class);

        self::assertIsArray($this->refundHydrator->hydrate($refund, $orderMock));

        self::assertEquals($expected, $this->refundHydrator->hydrate($refund, $orderMock));
    }

    /**
     * @return array[]
     */
    public function getHydratorTestData()
    {
        return [
            'Refund with amount 12.99, settlementAmount -12.99' => [
                $this->getExpectedData(12.99, -12.99),
                $this->getRefund(12.99, -12.99),
            ],
            'Refund with amount 12.99, settlementAmount null' => [
                $this->getExpectedData(12.99, null),
                $this->getRefund(12.99, null),
            ],
            'Refund with amount null, settlementAmount -12.99' => [
                $this->getExpectedData(null, -12.99),
                $this->getRefund(null, -12.99),
            ],
            'Refund with amount null, settlementAmount null' => [
                $this->getExpectedData(null, null),
                $this->getRefund(null, null),
            ],
            'Refund with status processing' => [
                $this->getExpectedData(12.99, -12.99, RefundStatus::PROCESSING),
                $this->getRefund(12.99, -12.99, RefundStatus::PROCESSING),
            ],
            'Refund with status pending' => [
                $this->getExpectedData(12.99, -12.99, RefundStatus::PENDING),
                $this->getRefund(12.99, -12.99, RefundStatus::PENDING),
            ],
            'Refund with status failed' => [
                $this->getExpectedData(12.99, -12.99, RefundStatus::FAILED),
                $this->getRefund(12.99, -12.99, RefundStatus::FAILED),
            ],
            'Refund with status refunded' => [
                $this->getExpectedData(12.99, -12.99, RefundStatus::REFUNDED),
                $this->getRefund(12.99, -12.99, RefundStatus::REFUNDED),
            ],
        ];
    }

    private function getExpectedData(?float $amount, ?float $settlementAmount, string $status = RefundStatus::QUEUED): array
    {
        if (! is_null($amount)) {
            $amount = [
                'value' => $amount,
                'currency' => 'EUR',
            ];
        }

        if (! is_null($settlementAmount)) {
            $settlementAmount = [
                'value' => $settlementAmount,
                'currency' => 'EUR',
            ];
        }

        return [
            'id' => 'foo',
            'orderId' => 'bar',
            'paymentId' => 'baz',
            'amount' => $amount,
            'settlementAmount' => $settlementAmount,
            'description' => 'description',
            'internalDescription' => null,
            'createdAt' => '2015-08-01T12:34:56+0100',
            'status' => $status,
            'isFailed' => $status == RefundStatus::FAILED,
            'isPending' => $status == RefundStatus::PENDING,
            'isProcessing' => $status == RefundStatus::PROCESSING,
            'isQueued' => $status == RefundStatus::QUEUED,
            'isTransferred' => $status == RefundStatus::REFUNDED,
            'metadata' => new \stdClass(),
        ];
    }

    private function getRefund(?float $amount, ?float $settlementAmount, string $status = RefundStatus::QUEUED): Refund
    {
        if (! is_null($amount)) {
            $amount = [
                'value' => $amount,
                'currency' => 'EUR',
            ];
        }

        if (! is_null($settlementAmount)) {
            $settlementAmount = [
                'value' => $settlementAmount,
                'currency' => 'EUR',
            ];
        }

        $refundMock = $this->createConfiguredMock(
            Refund::class,
            [
                'isQueued' => $status == RefundStatus::QUEUED,
                'isPending' => $status == RefundStatus::PENDING,
                'isProcessing' => $status == RefundStatus::PROCESSING,
                'isTransferred' => $status == RefundStatus::REFUNDED,
                'isFailed' => $status == RefundStatus::FAILED,
                'cancel' => null,
            ]
        );

        $refundMock->id = 'foo';
        $refundMock->orderId = 'bar';
        $refundMock->paymentId = 'baz';
        $refundMock->description = 'description';
        $refundMock->createdAt = '2015-08-01T12:34:56+0100';
        $refundMock->status = $status;
        $refundMock->_links = (object) [];
        $refundMock->amount = $amount ? (object) $amount : null;
        $refundMock->settlementAmount = $settlementAmount ? (object) $settlementAmount : null;

        return $refundMock;
    }
}
