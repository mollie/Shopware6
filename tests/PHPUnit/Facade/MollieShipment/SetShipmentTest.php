<?php declare(strict_types=1);

namespace MolliePayments\Tests\Facade\MollieShipment;

use Kiener\MolliePayments\Facade\MollieShipment;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MollieApi\OrderDataExtractor;
use Kiener\MolliePayments\Service\MollieApi\Shipment;
use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Kiener\MolliePayments\Service\OrderDeliveryService;
use Kiener\MolliePayments\Service\OrderService;
use Kiener\MolliePayments\Service\Transition\DeliveryTransitionService;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SetShipmentTest extends TestCase
{
    /**
     * @var MolliePaymentExtractor
     */
    private $extractor;

    /**
     * @var Order|\PHPUnit\Framework\MockObject\MockObject
     */
    private $deliveryTransitionService;

    /**
     * @var Order|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mollieApiOrderService;

    /**
     * @var Order|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mollieApiShipmentService;

    /**
     * @var OrderService|\PHPUnit\Framework\MockObject\MockObject
     */
    private $orderService;

    /**
     * @var OrderDeliveryService|\PHPUnit\Framework\MockObject\MockObject
     */
    private $orderDeliveryService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var OrderDataExtractor
     */
    private $orderDataExtractor;

    /**
     * @var MollieShipment
     */
    private $mollieShipment;

    /**
     * @var string
     */
    private $orderNumber;

    /**
     * @var Context
     */
    private $context;

    public function setup(): void
    {
        /** @var Context context */
        $this->context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->extractor = new MolliePaymentExtractor();
        $this->deliveryTransitionService = $this->createMock(DeliveryTransitionService::class);
        $this->mollieApiOrderService = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $this->mollieApiShipmentService = $this->getMockBuilder(Shipment::class)->disableOriginalConstructor()->getMock();
        $this->orderDeliveryService = $this->getMockBuilder(OrderDeliveryService::class)->disableOriginalConstructor()->getMock();
        $this->orderService = $this->getMockBuilder(OrderService::class)->disableOriginalConstructor()->getMock();

        $this->logger = new NullLogger();

        $this->orderDataExtractor = new OrderDataExtractor(
            $this->logger,
            $this->createMock(CustomerService::class)
        );

        $this->mollieShipment = new MollieShipment(
            $this->extractor,
            $this->deliveryTransitionService,
            $this->mollieApiOrderService,
            $this->mollieApiShipmentService,
            $this->orderDeliveryService,
            $this->orderService,
            $this->orderDataExtractor,
            $this->logger
        );
        $this->orderNumber = 'fooOrderNumber';
    }

    public function testInvalidDeliveryId(): void
    {
        $deliveryId = 'foo';
        $this->orderDeliveryService->method('getDelivery')->willReturn(null);

        // custom fields for shipping are never written
        $this->orderDeliveryService->expects($this->never())->method('updateCustomFields');
        // api call is never done
        $this->mollieApiOrderService->expects($this->never())->method('setShipment');
        // result value of facade is false
        self::assertFalse($this->mollieShipment->setShipment($deliveryId, $this->context));
    }

    public function testMissingOrder(): void
    {
        $delivery = $this->createDelivery(null);
        $deliveryId = $delivery->getId();
        $this->orderDeliveryService->method('getDelivery')->willReturn($delivery);

        // custom fields for shipping are never written
        $this->orderDeliveryService->expects($this->never())->method('updateCustomFields');
        // api call is never done
        $this->mollieApiOrderService->expects($this->never())->method('setShipment');
        // result value of facade is false
        self::assertFalse($this->mollieShipment->setShipment($deliveryId, $this->context));
    }

    public function testMissingCustomFieldsInOrder(): void
    {
        $order = $this->createOrder(null);
        $delivery = $this->createDelivery($order);
        $deliveryId = $delivery->getId();
        $this->orderDeliveryService->method('getDelivery')->willReturn($delivery);

        // custom fields for shipping are never written
        $this->orderDeliveryService->expects($this->never())->method('updateCustomFields');
        // api call is never done
        $this->mollieApiOrderService->expects($this->never())->method('setShipment');
        // result value of facade is false
        self::assertFalse($this->mollieShipment->setShipment($deliveryId, $this->context));
    }

    public function testMissingLastMollieTransaction(): void
    {
        $order = $this->createOrder(null);
        $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] = 'foo';
        $order->setCustomFields($customFields);
        $delivery = $this->createDelivery($order);
        $deliveryId = $delivery->getId();
        $this->orderDeliveryService->method('getDelivery')->willReturn($delivery);

        // custom fields for shipping are never written
        $this->orderDeliveryService->expects($this->never())->method('updateCustomFields');
        // api call is never done
        $this->mollieApiOrderService->expects($this->never())->method('setShipment');
        // result value of facade is false
        self::assertFalse($this->mollieShipment->setShipment($deliveryId, $this->context));
    }

    public function testThatOrderDeliveryCustomFieldsAreNotWrittenWhenApiCallUnsuccessful(): void
    {
        $transaction = $this->createTransaction('Kiener\MolliePayments\Handler\Method\FooMethod');
        $order = $this->createOrder($transaction);
        $mollieOrderId = 'foo';
        $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] = $mollieOrderId;
        $order->setCustomFields($customFields);
        $salesChannel = $this->getMockBuilder(SalesChannelEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannelId = 'bar';
        $salesChannel->method('getId')->willReturn($salesChannelId);
        $order->setSalesChannel($salesChannel);
        $order->setSalesChannelId($salesChannelId);
        $delivery = $this->createDelivery($order);
        $deliveryId = $delivery->getId();
        $this->orderDeliveryService->method('getDelivery')->willReturn($delivery);
        $this->mollieApiOrderService->method('setShipment')
            ->with($mollieOrderId, $salesChannelId)
            ->willReturn(false);

        // custom fields for shipping are never written
        $this->orderDeliveryService->expects($this->never())->method('updateCustomFields');

        // result value of facade is false
        self::assertFalse($this->mollieShipment->setShipment($deliveryId, $this->context));
    }

    public function testThatOrderDeliveryCustomFieldsAreWrittenWhenApiCallSuccessful(): void
    {
        $transaction = $this->createTransaction('Kiener\MolliePayments\Handler\Method\FooMethod');
        $order = $this->createOrder($transaction);
        $mollieOrderId = 'foo';
        $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] = $mollieOrderId;
        $order->setCustomFields($customFields);
        $salesChannelId = 'bar';
        $salesChannel = $this->getMockBuilder(SalesChannelEntity::class)->disableOriginalConstructor()->getMock();
        $salesChannel->method('getId')->willReturn($salesChannelId);
        $order->setSalesChannel($salesChannel);
        $order->setSalesChannelId($salesChannelId);
        $delivery = $this->createDelivery($order);
        $deliveryId = $delivery->getId();
        $this->orderDeliveryService->method('getDelivery')->willReturn($delivery);
        $this->mollieApiOrderService->method('setShipment')
            ->with($mollieOrderId, $salesChannelId)
            ->willReturn(true);

        // custom fields for shipping are written
        $this->orderDeliveryService->expects($this->once())
            ->method('updateCustomFields')
            ->with($delivery, [CustomFieldsInterface::DELIVERY_SHIPPED => true], $this->context);

        // result value of facade is true
        self::assertTrue($this->mollieShipment->setShipment($deliveryId, $this->context));
    }

    /**
     * create a delivery entity and set the order in delivery if given
     *
     * @param OrderEntity|null $order
     * @return OrderDeliveryEntity
     */
    private function createDelivery(?OrderEntity $order): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());

        if ($order instanceof OrderEntity) {
            $delivery->setOrder($order);
        }

        return $delivery;
    }

    /**
     * create an order entity and set the transaction in order if given
     *
     * @param OrderTransactionEntity|null $transaction
     * @return OrderEntity
     */
    private function createOrder(?OrderTransactionEntity $transaction): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setOrderNumber($this->orderNumber);
        $transactions = new OrderTransactionCollection([]);
        if ($transaction instanceof OrderTransactionEntity) {
            $transactions->add($transaction);
        }
        $order->setTransactions($transactions);

        return $order;
    }

    /**
     * create a transaction with a payment with given payment handler name
     *
     * @param string $paymentHandlerName
     * @return OrderTransactionEntity
     */
    private function createTransaction(string $paymentHandlerName): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setHandlerIdentifier($paymentHandlerName);
        $transaction->setCreatedAt(new \DateTime());
        $transaction->setPaymentMethod($paymentMethod);

        return $transaction;
    }
}
