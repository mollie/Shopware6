<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service;

use Kiener\MolliePayments\Factory\MollieApiFactory;
use Kiener\MolliePayments\Service\CustomFieldService;
use Kiener\MolliePayments\Service\RefundService;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Order;
use Mollie\Api\Resources\Payment;
use Mollie\Api\Resources\PaymentCollection;
use Mollie\Api\Resources\Refund;
use Mollie\Api\Resources\RefundCollection;
use Mollie\Api\Types\PaymentStatus;
use Mollie\Api\Types\RefundStatus;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;

class MollieRefundsServiceTest extends TestCase
{
    /**
     * Test getting correct refunded amount from RefundService;
     *
     * @param float $expected
     * @param bool $isMollieOrder
     * @param string|null $paymentStatus
     * @param float $refundedAmount
     * @dataProvider getAmountsTestData
     */
    public function testRefundedAmount(float $expected, bool $isMollieOrder, ?string $paymentStatus, float $refundedAmount)
    {
        $orderEntityMock = $this->getOrderEntityMock($isMollieOrder);
        $apiFactoryMock = $this->getApiFactoryMock($isMollieOrder, $paymentStatus, $refundedAmount);

        $refundService = new RefundService($apiFactoryMock);

        static::assertEquals($expected, $refundService->getRefundedAmount($orderEntityMock));
    }

    /**
     * Test getting correct refunded amount from RefundService;
     *
     * @param float $expected
     * @param bool $isMollieOrder
     * @param string|null $paymentStatus
     * @param float $remainingAmount
     * @dataProvider getAmountsTestData
     */
    public function testRemainingAmount(
        float $expected,
        bool $isMollieOrder,
        ?string $paymentStatus,
        float $remainingAmount
    ): void
    {
        $orderEntityMock = $this->getOrderEntityMock($isMollieOrder);
        $apiFactoryMock = $this->getApiFactoryMock($isMollieOrder, $paymentStatus, $remainingAmount);

        $refundService = new RefundService($apiFactoryMock);

        static::assertEquals($expected, $refundService->getRefundedAmount($orderEntityMock));
    }

    /**
     * @param int $expected
     * @param bool $isMollieOrder
     * @param string|null $paymentStatus
     * @param array $refunds
     * @dataProvider getRefundListTestData
     */
    public function testRefundList(
        int $expected,
        bool $isMollieOrder,
        ?string $paymentStatus,
        array $refunds
    ): void
    {
        $orderEntityMock = $this->getOrderEntityMock($isMollieOrder);
        $apiFactoryMock = $this->getApiFactoryMock($isMollieOrder, $paymentStatus, 0, $refunds);

        $refundService = new RefundService($apiFactoryMock);

        static::assertCount($expected, $refundService->getRefunds($orderEntityMock));
    }

    public function getAmountsTestData(): array
    {
        return [
            'Not a Mollie order' => [
                0,
                false,
                null,
                0
            ],
            'Mollie order, payment open' => [
                0,
                true,
                PaymentStatus::STATUS_OPEN,
                24.99
            ],
            'Mollie order, payment paid' => [
                24.99,
                true,
                PaymentStatus::STATUS_PAID,
                24.99
            ],
            'Mollie order, payment authorized' => [
                24.99,
                true,
                PaymentStatus::STATUS_AUTHORIZED,
                24.99
            ]
        ];
    }

    public function getRefundListTestData(): array
    {
        return [
            'Not a Mollie order' => [
                0,
                false,
                null,
                []
            ],
            'Mollie order, payment open' => [
                0,
                true,
                PaymentStatus::STATUS_OPEN,
                []
            ],
            'Mollie order, payment paid' => [
                1,
                true,
                PaymentStatus::STATUS_PAID,
                [
                    [
                        'status' => RefundStatus::STATUS_REFUNDED,
                        'amount' => 24.99
                    ]
                ]
            ],
            'Mollie order, payment authorized' => [
                1,
                true,
                PaymentStatus::STATUS_AUTHORIZED,
                [
                    [
                        'status' => RefundStatus::STATUS_REFUNDED,
                        'amount' => 24.99
                    ]
                ]
            ]
        ];
    }

    private function getApiFactoryMock(
        bool $isMollieOrder,
        ?string $paymentStatus,
        float $amount,
        array $refunds = []
    ): MollieApiFactory
    {
        $clientMock = $this->createMock(MollieApiClient::class);

        if ($isMollieOrder) {
            $paymentMock = $this->createConfiguredMock(
                Payment::class,
                [
                    'getAmountRefunded' => $amount,
                    'getAmountRemaining' => $amount,
                    'refunds' => $this->getRefundsCollectionMock($refunds)
                ]
            );
            $paymentMock->status = $paymentStatus;

            $paymentCollectionMock = $this->createConfiguredMock(
                PaymentCollection::class,
                ['getArrayCopy' => [$paymentMock]]
            );

            $orderMock = $this->createConfiguredMock(
                Order::class,
                ['payments' => $paymentCollectionMock]
            );

            $orderEndpointMock = $this->createConfiguredMock(
                OrderEndpoint::class,
                ['get' => $orderMock]
            );

            $clientMock->orders = $orderEndpointMock;
        }

        return $this->createConfiguredMock(
            MollieApiFactory::class,
            ['createClient' => $clientMock]
        );
    }

    private function getOrderEntityMock(bool $isMollieOrder): OrderEntity
    {
        return $this->createConfiguredMock(
            OrderEntity::class,
            [
                'getSalesChannelId' => 'foo',
                'getCustomFields' => $isMollieOrder
                    ? [
                        CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS => [
                            'order_id' => 'bar'
                        ]
                    ]
                    : null
            ]
        );
    }

    private function getRefundsCollectionMock(array $refunds = []): RefundCollection
    {
        $refundMocks = [];

        foreach ($refunds as $refund) {
            $refundMock = $this->createMock(Refund::class);

            $refundMock->id = 'foo_id';
            $refundMock->amount = (object)[
                'value' => $refund['amount'],
                'currency' => 'EUR'
            ];
            $refundMock->createdAt = date(DATE_ISO8601);
            $refundMock->description = 'Unit test refund';
            $refundMock->paymentId = 'bar_id';
            $refundMock->settlementAmount = (object)[
                'value' => -($refund['amount']),
                'currency' => 'EUR'
            ];
            $refundMock->status = $refund['status'];
            $refundMock->_links = (object)[];

            $refundMocks[] = $refundMock;
        }

        return $this->createConfiguredMock(
            RefundCollection::class,
            [
                'getArrayCopy' => $refundMocks
            ]
        );
    }
}
