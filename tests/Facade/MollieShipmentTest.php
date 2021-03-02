<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Facade;

use Kiener\MolliePayments\Facade\MollieShipment;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\MollieApi\Order;
use Kiener\MolliePayments\Service\MolliePaymentExtractor;
use Kiener\MolliePayments\Service\OrderDeliveryService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class MollieShipmentTest extends TestCase
{

    /**
     * @var MolliePaymentExtractor
     */
    private $extractor;

    /**
     * @var Order|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mollieApiOrderService;

    /**
     * @var OrderDeliveryService|\PHPUnit\Framework\MockObject\MockObject
     */
    private $orderDeliveryService;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var MollieShipment
     */
    private $mollieShipment;

    /**
     * @var string
     */
    private $orderNumber;

    public function setup(): void
    {
        $this->extractor = new MolliePaymentExtractor();
        $this->mollieApiOrderService = $this->getMockBuilder(Order::class)->disableOriginalConstructor()->getMock();
        $this->orderDeliveryService = $this->getMockBuilder(OrderDeliveryService::class)->disableOriginalConstructor()->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)->getMock();
        $this->mollieShipment = new MollieShipment(
            $this->extractor,
            $this->mollieApiOrderService,
            $this->orderDeliveryService,
            $this->logger
        );
        $this->orderNumber = 'fooOrderNumber';
    }

    public function testInvalidDeliveryId(): void
    {
        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $deliveryId = 'foo';
        $this->orderDeliveryService->method('getDelivery')->willReturn(null);

        $this->logger->expects($this->once())->method('debug')->with(
            sprintf('Order delivery with id %s could not be found in database', $deliveryId)
        );
        $this->orderDeliveryService->expects($this->never())->method('updateCustomFields');
        $this->mollieApiOrderService->expects($this->never())->method('setShipment');
        self::assertFalse($this->mollieShipment->setShipment($deliveryId, $context));
    }

    public function testMissingOrder(): void
    {
        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $delivery = $this->createDelivery(null);
        $deliveryId = $delivery->getId();
        $this->orderDeliveryService->method('getDelivery')->willReturn($delivery);

        $this->logger->expects($this->once())->method('debug')->with(
            sprintf('Loaded delivery with id %s does not have an order in database', $deliveryId)
        );
        $this->orderDeliveryService->expects($this->never())->method('updateCustomFields');
        $this->mollieApiOrderService->expects($this->never())->method('setShipment');
        self::assertFalse($this->mollieShipment->setShipment($deliveryId, $context));
    }

    public function testMissingCustomFieldsInOrder(): void
    {
        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $order = $this->createOrder(null);
        $delivery = $this->createDelivery($order);
        $deliveryId = $delivery->getId();
        $this->orderDeliveryService->method('getDelivery')->willReturn($delivery);

        $this->logger->expects($this->once())->method('debug')->with(
            sprintf('Mollie orderId does not exist in shopware order (%s)', (string)$order->getOrderNumber())
        );
        $this->orderDeliveryService->expects($this->never())->method('updateCustomFields');
        $this->mollieApiOrderService->expects($this->never())->method('setShipment');
        self::assertFalse($this->mollieShipment->setShipment($deliveryId, $context));
    }

    public function testMissingLastMollieTransaction(): void
    {
        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $order = $this->createOrder(null);
        $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] = 'foo';
        $order->setCustomFields($customFields);
        $delivery = $this->createDelivery($order);
        $deliveryId = $delivery->getId();
        $this->orderDeliveryService->method('getDelivery')->willReturn($delivery);

        $this->logger->expects($this->once())->method('info')->with(
            sprintf(
                'The last transaction of the order (%s) is not a mollie payment! No shipment will be sent to mollie',
                (string)$order->getOrderNumber()
            )
        );
        $this->orderDeliveryService->expects($this->never())->method('updateCustomFields');
        $this->mollieApiOrderService->expects($this->never())->method('setShipment');
        self::assertFalse($this->mollieShipment->setShipment($deliveryId, $context));
    }

    public function testThatOrderDeliveryCustomFieldsAreNotWrittenWhenApiCallUnsuccessful(): void
    {
        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $transaction = $this->createTransaction('Kiener\MolliePayments\Handler\Method\FooMethod');
        $order = $this->createOrder($transaction);
        $mollieOrderId = 'foo';
        $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] = $mollieOrderId;
        $order->setCustomFields($customFields);
        $salesChannelId = 'bar';
        $order->setSalesChannelId($salesChannelId);
        $delivery = $this->createDelivery($order);
        $deliveryId = $delivery->getId();
        $this->orderDeliveryService->method('getDelivery')->willReturn($delivery);
        $this->mollieApiOrderService->method('setShipment')
            ->with($mollieOrderId, $salesChannelId)
            ->willReturn(false);

        $this->orderDeliveryService->expects($this->never())->method('updateCustomFields');
        $this->logger->expects($this->never())->method('info');
        $this->logger->expects($this->never())->method('debug');
        self::assertFalse($this->mollieShipment->setShipment($deliveryId, $context));
    }

    public function testThatOrderDeliveryCustomFieldsAreWrittenWhenApiCallSuccessful(): void
    {
        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $transaction = $this->createTransaction('Kiener\MolliePayments\Handler\Method\FooMethod');
        $order = $this->createOrder($transaction);
        $mollieOrderId = 'foo';
        $customFields[CustomFieldsInterface::MOLLIE_KEY][CustomFieldsInterface::ORDER_KEY] = $mollieOrderId;
        $order->setCustomFields($customFields);
        $salesChannelId = 'bar';
        $order->setSalesChannelId($salesChannelId);
        $delivery = $this->createDelivery($order);
        $deliveryId = $delivery->getId();
        $this->orderDeliveryService->method('getDelivery')->willReturn($delivery);
        $this->mollieApiOrderService->method('setShipment')
            ->with($mollieOrderId, $salesChannelId)
            ->willReturn(true);

        $this->orderDeliveryService->expects($this->once())
            ->method('updateCustomFields')
            ->with($delivery, [CustomFieldsInterface::DELIVERY_SHIPPED => true], $context);
        $this->logger->expects($this->never())->method('info');
        $this->logger->expects($this->never())->method('debug');
        self::assertTrue($this->mollieShipment->setShipment($deliveryId, $context));
    }

    private function createDelivery(?OrderEntity $order): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());

        if ($order instanceof OrderEntity) {
            $delivery->setOrder($order);
        }

        return $delivery;
    }

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

    private function createTransaction(string $methodName): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId(Uuid::randomHex());
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId(Uuid::randomHex());
        $paymentMethod->setHandlerIdentifier($methodName);
        $transaction->setCreatedAt(new \DateTime());
        $transaction->setPaymentMethod($paymentMethod);

        return $transaction;
    }
}
