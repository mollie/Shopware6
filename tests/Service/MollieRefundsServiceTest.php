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
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\OrderEntity;

class MollieRefundsServiceTest extends TestCase
{


    public function setUp(): void
    {
        $logger = new NullLogger();
    }

    /**
     * Test refunded amount with open payments
     */
    public function testGetRefundedAmountOpen()
    {
        $refundService = new RefundService($this->getApiMock(PaymentStatus::STATUS_OPEN));

        $orderEntity = $this->getOrderEntityMock(true);

        $refundAmount = $refundService->getRefundedAmount($orderEntity);

        static::assertEquals(0, $refundAmount );
    }

    /**
     * Test refunded amount with paid payments
     */
    public function testGetRefundedAmountPaid()
    {
        $refundService = new RefundService($this->getApiMock(PaymentStatus::STATUS_PAID));

        $orderEntity = $this->getOrderEntityMock(true);

        $refundAmount = $refundService->getRefundedAmount($orderEntity);

        static::assertEquals(24.99, $refundAmount );
    }

    /**
     * Test refunded amount with authorized payments
     */
    public function testGetRefundedAmountAuthorized()
    {
        $refundService = new RefundService($this->getApiMock(PaymentStatus::STATUS_AUTHORIZED));

        $orderEntity = $this->getOrderEntityMock(true);

        $refundAmount = $refundService->getRefundedAmount($orderEntity);

        static::assertEquals(24.99, $refundAmount );
    }

    /**
     * Test refunded amount without mollieId
     */
    public function testGetRefundedAmountNotMollieOrder()
    {
        $refundService = new RefundService($this->getApiMock(PaymentStatus::STATUS_OPEN));

        $orderEntity = $this->getOrderEntityMock(false);

        $refundAmount = $refundService->getRefundedAmount($orderEntity);

        static::assertEquals(0, $refundAmount );
    }

    /**
     * Test remaining amount with open payments
     */
    public function testGetRemainingAmountOpen()
    {
        $refundService = new RefundService($this->getApiMock(PaymentStatus::STATUS_OPEN));

        $orderEntity = $this->getOrderEntityMock(true);

        $refundAmount = $refundService->getRemainingAmount($orderEntity);

        static::assertEquals(0, $refundAmount );
    }

    /**
     * Test remaining amount with paid payments
     */
    public function testGetRemainingAmountPaid()
    {
        $refundService = new RefundService($this->getApiMock(PaymentStatus::STATUS_PAID));

        $orderEntity = $this->getOrderEntityMock(true);

        $refundAmount = $refundService->getRemainingAmount($orderEntity);

        static::assertEquals(48.98, $refundAmount );
    }

    /**
     * Test remaining amount with authorized payments
     */
    public function testGetRemainingAmountAuthorized()
    {
        $refundService = new RefundService($this->getApiMock(PaymentStatus::STATUS_AUTHORIZED));

        $orderEntity = $this->getOrderEntityMock(true);

        $refundAmount = $refundService->getRemainingAmount($orderEntity);

        static::assertEquals(48.98, $refundAmount );
    }

    /**
     * Test remaining amount without mollieId
     */
    public function testGetRemainingAmountNotMollieOrder()
    {
        $refundService = new RefundService($this->getApiMock(PaymentStatus::STATUS_OPEN));

        $orderEntity = $this->getOrderEntityMock(false);

        $refundAmount = $refundService->getRemainingAmount($orderEntity);

        static::assertEquals(0, $refundAmount );
    }

    /**
     * Test refunds list
     */
    public function testGetRefundsList()
    {
        $refundService = new RefundService($this->getApiMock(PaymentStatus::STATUS_PAID));

        $refunds = $refundService->getRefunds($this->getOrderEntityMock(true));

        static::assertIsArray($refunds);
        static::assertCount(1, $refunds);
    }

    /**
     * @psalm-param null|callable(Method): void
     */
    private function getApiMock($paymentStatus, ?callable $configureMethod = null): MollieApiFactory
    {
        $apiFactoryMock = static::getMockBuilder(MollieApiFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $clientMock = static::getMockBuilder(MollieApiClient::class)
            ->getMock();
        $orderEndpointMock = static::getMockBuilder(OrderEndpoint::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderMock = static::getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->getMock();
        $paymentCollectionMock = static::getMockBuilder(PaymentCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $paymentMock = static::getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $clientMock->orders = $orderEndpointMock;
        $paymentMock->status = $paymentStatus;

        $apiFactoryMock->method('createClient')->willReturn($clientMock);
        $orderEndpointMock->method('get')->willReturn($orderMock);
        $orderMock->method('payments')->willReturn($paymentCollectionMock);
        $paymentCollectionMock->method('getArrayCopy')->willReturn([$paymentMock]);
        $paymentMock->method('getAmountRefunded')->willReturn(24.99);
        $paymentMock->method('getAmountRemaining')->willReturn(48.98);
        $paymentMock->method('refunds')->willReturn($this->getRefundsCollectionMock());

        return $apiFactoryMock;
    }

    private function getOrderEntityMock(bool $isMollieOrder):OrderEntity
    {
        $orderEntityMock = static::getMockBuilder(OrderEntity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderEntityMock->method('getSalesChannelId')->willReturn('string');

        if($isMollieOrder) {
            $orderEntityMock->method('getCustomFields')->willReturn([CustomFieldService::CUSTOM_FIELDS_KEY_MOLLIE_PAYMENTS => ['order_id' => 'testkey']]);
        }
        return $orderEntityMock;
    }

    private function getRefundsCollectionMock():RefundCollection
    {
        $refundCollectionMock = static::getMockBuilder(RefundCollection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $refundMock = static::getMockBuilder(Refund::class)
            ->disableOriginalConstructor()
            ->getMock();

        $refundMock->id = 'test_id';
        $refundMock->amount = (object) [
            'value' => 24.99,
            'currency' => 'EUR'
            ];
        $refundMock->createdAt = '2013-12-25T10:30:54+00:00';
        $refundMock->description = 'Unit test refund';
        $refundMock->paymentId = 'tr_123456';
        $refundMock->settlementAmount = (object)[
            'value' => -24.99,
            'currency' => 'EUR'
        ];
        $refundMock->status = RefundStatus::STATUS_REFUNDED;
        $refundMock->_links = (object)[];

        $refundCollectionMock->method('getArrayCopy')->willReturn([$refundMock]);

        return $refundCollectionMock;
    }
}
