<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Hydrator;

use Kiener\MolliePayments\Hydrator\RefundHydrator;
use Mollie\Api\Resources\Refund;
use Mollie\Api\Types\RefundStatus;
use MolliePayments\Tests\Fakes\FakeMollieRefund;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('getHydratorTestData')]
    public function testRefundHydrator(array $expected, Refund $refund)
    {
        $orderMock = $this->createMock(OrderEntity::class);

        self::assertIsArray($this->refundHydrator->hydrate($refund, $orderMock));

        self::assertEquals($expected, $this->refundHydrator->hydrate($refund, $orderMock));
    }

    /**
     * @return array[]
     */
    public static function getHydratorTestData()
    {
        return [
            'Refund with amount 12.99, settlementAmount -12.99' => [
                self::getExpectedData(12.99, -12.99),
                self::getRefund(12.99, -12.99),
            ],
            'Refund with amount 12.99, settlementAmount null' => [
                self::getExpectedData(12.99, null),
                self::getRefund(12.99, null),
            ],
            'Refund with amount null, settlementAmount -12.99' => [
                self::getExpectedData(null, -12.99),
                self::getRefund(null, -12.99),
            ],
            'Refund with amount null, settlementAmount null' => [
                self::getExpectedData(null, null),
                self::getRefund(null, null),
            ],
            'Refund with status processing' => [
                self::getExpectedData(12.99, -12.99, RefundStatus::STATUS_PROCESSING),
                self::getRefund(12.99, -12.99, RefundStatus::STATUS_PROCESSING),
            ],
            'Refund with status pending' => [
                self::getExpectedData(12.99, -12.99, RefundStatus::STATUS_PENDING),
                self::getRefund(12.99, -12.99, RefundStatus::STATUS_PENDING),
            ],
            'Refund with status failed' => [
                self::getExpectedData(12.99, -12.99, RefundStatus::STATUS_FAILED),
                self::getRefund(12.99, -12.99, RefundStatus::STATUS_FAILED),
            ],
            'Refund with status refunded' => [
                self::getExpectedData(12.99, -12.99, RefundStatus::STATUS_REFUNDED),
                self::getRefund(12.99, -12.99, RefundStatus::STATUS_REFUNDED),
            ],
        ];
    }

    private static function getExpectedData(?float $amount, ?float $settlementAmount, string $status = RefundStatus::STATUS_QUEUED): array
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
            'isFailed' => $status == RefundStatus::STATUS_FAILED,
            'isPending' => $status == RefundStatus::STATUS_PENDING,
            'isProcessing' => $status == RefundStatus::STATUS_PROCESSING,
            'isQueued' => $status == RefundStatus::STATUS_QUEUED,
            'isTransferred' => $status == RefundStatus::STATUS_REFUNDED,
            'metadata' => new \stdClass(),
        ];
    }

    private static function getRefund(?float $amount, ?float $settlementAmount, string $status = RefundStatus::STATUS_QUEUED): Refund
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

        $refundMock = new FakeMollieRefund($status);

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
