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
use Shopware\Core\System\Currency\CurrencyEntity;

class MollieRefundsServiceTest extends TestCase
{
    /**
     * @param bool $expected
     * @param bool $isMollieOrder
     * @param string|null $paymentStatus
     * @dataProvider getRefundTestData
     */
    public function testRefunds(
        bool $expected,
        bool $isMollieOrder,
        ?string $paymentStatus
    ): void
    {
        $orderEntityMock = $this->getOrderEntityMock($isMollieOrder);
        $apiFactoryMock = $this->getApiFactoryMock($isMollieOrder, $paymentStatus, 0);

        $refundService = new RefundService($apiFactoryMock);

        static::assertEquals($expected, $refundService->refund($orderEntityMock, 24.99));
    }

    /**
     * @param bool $expected
     * @param bool $isMollieOrder
     * @param string|null $paymentStatus
     * @dataProvider getRefundCancelTestData
     */
    public function testCancelRefunds(
        bool $expected,
        bool $isMollieOrder,
        ?string $paymentStatus,
        array $refunds
    ): void
    {
        $orderEntityMock = $this->getOrderEntityMock($isMollieOrder);
        $apiFactoryMock = $this->getApiFactoryMock($isMollieOrder, $paymentStatus, 0, $refunds);

        $refundService = new RefundService($apiFactoryMock);

        static::assertEquals($expected, $refundService->cancel($orderEntityMock, 'foo'));
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
     * Test getting correct refunded amount from RefundService;
     *
     * @param float $expected
     * @param bool $isMollieOrder
     * @param string|null $paymentStatus
     * @param float $refundedAmount
     * @dataProvider getAmountsTestData
     */
    public function testRefundedAmount(
        float $expected,
        bool $isMollieOrder,
        ?string $paymentStatus,
        float $refundedAmount
    ): void
    {
        $orderEntityMock = $this->getOrderEntityMock($isMollieOrder);
        $apiFactoryMock = $this->getApiFactoryMock($isMollieOrder, $paymentStatus, $refundedAmount);

        $refundService = new RefundService($apiFactoryMock);

        static::assertEquals($expected, $refundService->getRefundedAmount($orderEntityMock));
    }

    public function getRefundTestData(): array
    {
        return [
            'Not a Mollie order' => [
                false,
                false,
                null
            ],
            'Mollie order, payment open' => [
                false,
                true,
                PaymentStatus::STATUS_OPEN
            ],
            'Mollie order, payment paid' => [
                true,
                true,
                PaymentStatus::STATUS_PAID
            ],
            'Mollie order, payment authorized' => [
                true,
                true,
                PaymentStatus::STATUS_AUTHORIZED
            ]
        ];
    }

    public function getRefundCancelTestData(): array
    {
        return [
            'Not a Mollie order' => [
                false,
                false,
                null,
                []
            ],
            'Mollie order, payment open' => [
                false,
                true,
                PaymentStatus::STATUS_OPEN,
                []
            ],
            'Mollie order, payment paid, no refund' => [
                false,
                true,
                PaymentStatus::STATUS_PAID,
                []
            ],
            'Mollie order, payment paid, refund queued' => [
                true,
                true,
                PaymentStatus::STATUS_PAID,
                [
                    [
                        'status' => RefundStatus::STATUS_QUEUED,
                        'amount' => 24.99
                    ]
                ]
            ],
            'Mollie order, payment paid, refund pending' => [
                true,
                true,
                PaymentStatus::STATUS_PAID,
                [
                    [
                        'status' => RefundStatus::STATUS_PENDING,
                        'amount' => 24.99
                    ]
                ]
            ],
            'Mollie order, payment paid, refund processing' => [
                false,
                true,
                PaymentStatus::STATUS_PAID,
                [
                    [
                        'status' => RefundStatus::STATUS_PROCESSING,
                        'amount' => 24.99
                    ]
                ]
            ],
            'Mollie order, payment paid, refund refunded' => [
                false,
                true,
                PaymentStatus::STATUS_PAID,
                [
                    [
                        'status' => RefundStatus::STATUS_REFUNDED,
                        'amount' => 24.99
                    ]
                ]
            ],
            'Mollie order, payment authorized, refund queued' => [
                true,
                true,
                PaymentStatus::STATUS_AUTHORIZED,
                [
                    [
                        'status' => RefundStatus::STATUS_QUEUED,
                        'amount' => 24.99
                    ]
                ]
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

    /**
     * @param bool $isMollieOrder
     * @param string|null $paymentStatus
     * @param float $amount
     * @param array $refunds
     * @return MollieApiFactory
     */
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
                    'refunds' => $this->getRefundsCollectionMock($refunds),
                    'refund' => $this->getRefundMock(),
                    'getRefund' => $this->getRefundMock(
                        $refunds[0]['status'] ?? RefundStatus::STATUS_REFUNDED,
                        $refunds[0]['amount'] ?? 0
                    )
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

    /**
     * @param bool $isMollieOrder
     * @return OrderEntity
     */
    private function getOrderEntityMock(bool $isMollieOrder): OrderEntity
    {
        $currencyMock = $this->createConfiguredMock(
            CurrencyEntity::class,
            ['getIsoCode' => 'EUR']
        );

        return $this->createConfiguredMock(
            OrderEntity::class,
            [
                'getSalesChannelId' => 'foo',
                'getOrderNumber' => 'bar',
                'getCurrency' => $currencyMock,
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

    /**
     * @param array $refunds
     * @return RefundCollection
     */
    private function getRefundsCollectionMock(array $refunds = []): RefundCollection
    {
        $refundMocks = [];

        foreach ($refunds as $refund) {
            $refundMocks[] = $this->getRefundMock($refund['status'], $refund['amount']);
        }

        return $this->createConfiguredMock(
            RefundCollection::class,
            [
                'getArrayCopy' => $refundMocks
            ]
        );
    }

    /**
     * @param string $status
     * @param float $amount
     * @return Refund
     */
    private function getRefundMock(string $status = RefundStatus::STATUS_QUEUED, float $amount = 0.0): Refund
    {
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

        $refundMock->id = 'foo_id';
        $refundMock->amount = (object)[
            'value' => $amount,
            'currency' => 'EUR'
        ];
        $refundMock->createdAt = date(DATE_ISO8601);
        $refundMock->description = 'Unit test refund';
        $refundMock->paymentId = 'bar_id';
        $refundMock->settlementAmount = (object)[
            'value' => -($amount),
            'currency' => 'EUR'
        ];
        $refundMock->status = $status;
        $refundMock->_links = (object)[];

        return $refundMock;
    }
}
