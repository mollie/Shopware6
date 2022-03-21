<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Hydrator;

use Kiener\MolliePayments\Hydrator\RefundHydrator;
use Mollie\Api\Resources\Refund;
use Mollie\Api\Types\RefundStatus;
use PHPUnit\Framework\TestCase;

class RefundHydratorTest extends TestCase
{
    /**
     * @var RefundHydrator
     */
    private $refundHydrator;


    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->refundHydrator = new RefundHydrator();
    }

    /**
     * @param array $expected
     * @param Refund $refund
     * @dataProvider getHydratorTestData
     */
    public function testRefundHydrator(array $expected, Refund $refund)
    {
        self::assertIsArray($this->refundHydrator->hydrate($refund));
        self::assertEquals($expected, $this->refundHydrator->hydrate($refund));
    }

    /**
     * @return array[]
     */
    public function getHydratorTestData()
    {
        return [
            'Refund with amount 12.99, settlementAmount -12.99' => [
                $this->getExpectedData(12.99, -12.99),
                $this->getRefund(12.99, -12.99)
            ],
            'Refund with amount 12.99, settlementAmount null' => [
                $this->getExpectedData(12.99, null),
                $this->getRefund(12.99, null)
            ],
            'Refund with amount null, settlementAmount -12.99' => [
                $this->getExpectedData(null, -12.99),
                $this->getRefund(null, -12.99)
            ],
            'Refund with amount null, settlementAmount null' => [
                $this->getExpectedData(null, null),
                $this->getRefund(null, null)
            ],
            'Refund with status processing' => [
                $this->getExpectedData(12.99, -12.99, RefundStatus::STATUS_PROCESSING),
                $this->getRefund(12.99, -12.99, RefundStatus::STATUS_PROCESSING)
            ],
            'Refund with status pending' => [
                $this->getExpectedData(12.99, -12.99, RefundStatus::STATUS_PENDING),
                $this->getRefund(12.99, -12.99, RefundStatus::STATUS_PENDING)
            ],
            'Refund with status failed' => [
                $this->getExpectedData(12.99, -12.99, RefundStatus::STATUS_FAILED),
                $this->getRefund(12.99, -12.99, RefundStatus::STATUS_FAILED)
            ],
            'Refund with status refunded' => [
                $this->getExpectedData(12.99, -12.99, RefundStatus::STATUS_REFUNDED),
                $this->getRefund(12.99, -12.99, RefundStatus::STATUS_REFUNDED)
            ],
        ];
    }

    /**
     * @param float|null $amount
     * @param float|null $settlementAmount
     * @param string $status
     * @return array
     */
    private function getExpectedData(?float $amount, ?float $settlementAmount, string $status = RefundStatus::STATUS_QUEUED): array
    {
        if (!is_null($amount)) {
            $amount = [
                'value' => $amount,
                'currency' => 'EUR'
            ];
        }

        if (!is_null($settlementAmount)) {
            $settlementAmount = [
                'value' => $settlementAmount,
                'currency' => 'EUR'
            ];
        }

        return [
            'id' => 'foo',
            'orderId' => 'bar',
            'paymentId' => 'baz',
            'amount' => $amount,
            'settlementAmount' => $settlementAmount,
            'description' => 'description',
            'createdAt' => '2015-08-01T12:34:56+0100',
            'status' => $status,
            'isFailed' => $status == RefundStatus::STATUS_FAILED,
            'isPending' => $status == RefundStatus::STATUS_PENDING,
            'isProcessing' => $status == RefundStatus::STATUS_PROCESSING,
            'isQueued' => $status == RefundStatus::STATUS_QUEUED,
            'isTransferred' => $status == RefundStatus::STATUS_REFUNDED,
            'metadata' => null,
        ];
    }

    private function getRefund(?float $amount, ?float $settlementAmount, string $status = RefundStatus::STATUS_QUEUED): Refund
    {
        if (!is_null($amount)) {
            $amount = [
                'value' => $amount,
                'currency' => 'EUR'
            ];
        }

        if (!is_null($settlementAmount)) {
            $settlementAmount = [
                'value' => $settlementAmount,
                'currency' => 'EUR'
            ];
        }

        $refundMock = $this->createConfiguredMock(
            Refund::class,
            [
                'isQueued' => $status == RefundStatus::STATUS_QUEUED,
                'isPending' => $status == RefundStatus::STATUS_PENDING,
                'isProcessing' => $status == RefundStatus::STATUS_PROCESSING,
                'isTransferred' => $status == RefundStatus::STATUS_REFUNDED,
                'isFailed' => $status == RefundStatus::STATUS_FAILED,
                'cancel' => null,
            ]
        );

        $refundMock->id = 'foo';
        $refundMock->orderId = 'bar';
        $refundMock->paymentId = 'baz';
        $refundMock->description = 'description';
        $refundMock->createdAt = '2015-08-01T12:34:56+0100';
        $refundMock->status = $status;
        $refundMock->_links = (object)[];
        $refundMock->amount = $amount ? (object)$amount : null;
        $refundMock->settlementAmount = $settlementAmount ? (object)$settlementAmount : null;

        return $refundMock;
    }
}
