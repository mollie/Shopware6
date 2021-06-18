<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Hydrator;

use Kiener\MolliePayments\Hydrator\RefundHydrator;
use Mollie\Api\Resources\Refund;
use Mollie\Api\Types\RefundStatus;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\Random;

class RefundHydratorTest extends TestCase
{
    /**
     * @param array $expected
     * @param Refund $refund
     * @dataProvider getHydratorTestData
     */
    public function testRefundHydrator(
        array $expected,
        Refund $refund
    )
    {
        self::assertIsArray(RefundHydrator::hydrate($refund));
        self::assertEquals($expected, RefundHydrator::hydrate($refund));
    }

    public function getHydratorTestData()
    {
        return [
            'Refund with amount 12.99, settlementAmount -12.99' => $this->createRefundTestData(12.99, -12.99),
            'Refund with amount 12.99, settlementAmount null' => $this->createRefundTestData(12.99, null),
            'Refund with amount null, settlementAmount -12.99' => $this->createRefundTestData(null, -12.99),
            'Refund with amount null, settlementAmount null' => $this->createRefundTestData(null, null),
        ];
    }

    private function createRefundTestData(?float $amount, ?float $settlementAmount): array
    {
        $id = 're_' . Random::getAlphanumericString(10);
        $orderId = 'ord_' . Random::getAlphanumericString(6);
        $paymentId = 'tr_' . Random::getAlphanumericString(10);
        $description = Random::getAlphanumericString(100);
        $createdAt = date(DATE_ISO8601);
        $status = Random::getRandomArrayElement([RefundStatus::STATUS_QUEUED, RefundStatus::STATUS_PENDING, RefundStatus::STATUS_FAILED, RefundStatus::STATUS_PROCESSING, RefundStatus::STATUS_REFUNDED]);

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

        $refundArray = [
            'id' => $id,
            'orderId' => $orderId,
            'paymentId' => $paymentId,
            'amount' => $amount,
            'settlementAmount' => $settlementAmount,
            'description' => $description,
            'createdAt' => $createdAt,
            'status' => $status,
            'isFailed' => $status == RefundStatus::STATUS_FAILED,
            'isPending' => $status == RefundStatus::STATUS_PENDING,
            'isProcessing' => $status == RefundStatus::STATUS_PROCESSING,
            'isQueued' => $status == RefundStatus::STATUS_QUEUED,
            'isTransferred' => $status == RefundStatus::STATUS_REFUNDED,
        ];

        $refundMock = $this->createConfiguredMock(
            Refund::class,
            [
                'isQueued' => $status == RefundStatus::STATUS_QUEUED,
                'isPending' => $status == RefundStatus::STATUS_PENDING,
                'isProcessing' => $status == RefundStatus::STATUS_PROCESSING,
                'isTransferred' => $status == RefundStatus::STATUS_REFUNDED,
                'isFailed' => $status == RefundStatus::STATUS_FAILED,
                'cancel' => null
            ]
        );

        $refundMock->id = $id;
        $refundMock->orderId = $orderId;
        $refundMock->paymentId = $paymentId;
        $refundMock->description = $description;
        $refundMock->createdAt = $createdAt;
        $refundMock->status = $status;
        $refundMock->_links = (object)[];
        $refundMock->amount = $amount ? (object)$amount : null;
        $refundMock->settlementAmount = $settlementAmount ? (object)$settlementAmount : null;

        return [$refundArray, $refundMock];
    }
}
