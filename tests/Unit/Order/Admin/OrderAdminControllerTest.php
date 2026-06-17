<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Order\Admin;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Order\Admin\OrderAdminController;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(OrderAdminController::class)]
final class OrderAdminControllerTest extends TestCase
{
    private Context $context;

    public function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    public function testCancelItemIsEmptyForPaymentsApiOrder(): void
    {
        $order = $this->buildOrder('pay-xxx', null);

        $repository = new FakeOrderSearchRepository();
        $repository->add($order);

        $gateway = new FakeGateway();
        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway);

        $response = $controller->details('order-1', $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertSame([], $body['cancelItem']);
    }

    public function testCancelItemContainsCancelableLineItemsForOrdersApiOrder(): void
    {
        $order = $this->buildOrder('pay-xxx', 'ord-xxx');

        $repository = new FakeOrderSearchRepository();
        $repository->add($order);

        $gateway = new FakeGateway();
        $mollieOrder = new Order('ord-xxx', '', [
            $this->buildCancelableLineItem('shopware-line-1', 'mollie-line-1', 2),
        ]);
        $gateway->withOrder($mollieOrder);

        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway);

        $response = $controller->details('order-1', $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('shopware-line-1', $body['cancelItem']);
        $lineStatus = $body['cancelItem']['shopware-line-1'];
        $this->assertSame('ord-xxx', $lineStatus['mollieOrderId']);
        $this->assertSame('mollie-line-1', $lineStatus['mollieId']);
        $this->assertTrue($lineStatus['isCancelable']);
        $this->assertSame(2, $lineStatus['cancelableQuantity']);
    }

    public function testCancelItemIsEmptyWhenMollieApiFails(): void
    {
        $order = $this->buildOrder('pay-xxx', 'ord-xxx');

        $repository = new FakeOrderSearchRepository();
        $repository->add($order);

        $gateway = new FakeGateway();
        $gateway->withGetOrderException();

        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway);

        $response = $controller->details('order-1', $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertSame([], $body['cancelItem']);
    }

    public function testShippingStatusIsEmptyForPaymentsApiOrder(): void
    {
        $order = $this->buildOrder('pay-xxx', null);

        $repository = new FakeOrderSearchRepository();
        $repository->add($order);

        $gateway = new FakeGateway();
        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway);

        $response = $controller->details('order-1', $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertSame([], $body['shipping']['status']);
    }

    public function testShippingStatusContainsShippableLineItems(): void
    {
        $order = $this->buildOrder('pay-xxx', 'ord-xxx');

        $repository = new FakeOrderSearchRepository();
        $repository->add($order);

        $gateway = new FakeGateway();
        $mollieOrder = new Order('ord-xxx', '', [
            $this->buildShippableLineItem('shopware-line-1', 'mollie-line-1', shippable: 2, shipped: 1),
        ]);
        $gateway->withOrder($mollieOrder);

        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway);

        $response = $controller->details('order-1', $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertArrayHasKey('shopware-line-1', $body['shipping']['status']);
        $lineStatus = $body['shipping']['status']['shopware-line-1'];
        $this->assertSame('ord-xxx', $lineStatus['mollieOrderId']);
        $this->assertSame('mollie-line-1', $lineStatus['mollieId']);
        $this->assertTrue($lineStatus['isShippable']);
        $this->assertSame(2, $lineStatus['shippableQuantity']);
        $this->assertSame(1, $lineStatus['quantityShipped']);
    }

    public function testShippingTotalAggregatesAcrossLines(): void
    {
        $order = $this->buildOrder('pay-xxx', 'ord-xxx');

        $repository = new FakeOrderSearchRepository();
        $repository->add($order);

        $gateway = new FakeGateway();
        $line1 = $this->buildShippableLineItem('shopware-line-1', 'mollie-line-1', shippable: 3, shipped: 1);
        $line1->setAmountShipped(new Money(10.0, 'EUR'));
        $line2 = $this->buildShippableLineItem('shopware-line-2', 'mollie-line-2', shippable: 2, shipped: 2);
        $line2->setAmountShipped(new Money(20.0, 'EUR'));
        $mollieOrder = new Order('ord-xxx', '', [$line1, $line2]);
        $gateway->withOrder($mollieOrder);

        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway);

        $response = $controller->details('order-1', $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $total = $body['shipping']['total'];
        $this->assertSame('30.00', $total['amount']);
        $this->assertSame(3, $total['quantity']);
        $this->assertSame(5, $total['shippable']);
    }

    private function buildOrder(string $molliePaymentId, ?string $mollieOrderId): OrderEntity
    {
        $payment = new Payment($molliePaymentId);
        if ($mollieOrderId !== null) {
            $payment->setOrderId($mollieOrderId);
        }

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->addExtension(Mollie::EXTENSION, $payment);

        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setSalesChannelId('sales-channel-1');
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        return $order;
    }

    private function buildCancelableLineItem(string $shopwareId, string $mollieId, int $cancelableQty): LineItem
    {
        $line = new LineItem('product', 1, new Money(10.0, 'EUR'), new Money(10.0, 'EUR'));
        $line->setId($mollieId);
        $line->setShopwareLineItemId($shopwareId);
        $line->setCancelableQuantity($cancelableQty);

        return $line;
    }

    private function buildShippableLineItem(string $shopwareId, string $mollieId, int $shippable, int $shipped): LineItem
    {
        $line = new LineItem('product', 1, new Money(10.0, 'EUR'), new Money(10.0, 'EUR'));
        $line->setId($mollieId);
        $line->setShopwareLineItemId($shopwareId);
        $line->setShippableQuantity($shippable);
        $line->setQuantityShipped($shipped);

        return $line;
    }
}
