<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Order\Admin;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Order\Admin\OrderAdminController;
use Mollie\Shopware\Component\Order\Admin\OrderAdminStatusBuilder;
use Mollie\Shopware\Component\Order\Admin\OrderPaymentRecovery;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

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
        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway, new OrderAdminStatusBuilder(), new OrderPaymentRecovery($repository));

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
        $mollieOrder = new Order('ord-xxx', '', null, [
            $this->buildCancelableLineItem('shopware-line-1', 'mollie-line-1', 2),
        ]);
        $gateway->withOrder($mollieOrder);

        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway, new OrderAdminStatusBuilder(), new OrderPaymentRecovery($repository));

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

        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway, new OrderAdminStatusBuilder(), new OrderPaymentRecovery($repository));

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
        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway, new OrderAdminStatusBuilder(), new OrderPaymentRecovery($repository));

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
        $mollieOrder = new Order('ord-xxx', '', null, [
            $this->buildShippableLineItem('shopware-line-1', 'mollie-line-1', shippable: 2, shipped: 1),
        ]);
        $gateway->withOrder($mollieOrder);

        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway, new OrderAdminStatusBuilder(), new OrderPaymentRecovery($repository));

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
        $mollieOrder = new Order('ord-xxx', '', null, [$line1, $line2]);
        $gateway->withOrder($mollieOrder);

        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway, new OrderAdminStatusBuilder(), new OrderPaymentRecovery($repository));

        $response = $controller->details('order-1', $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $total = $body['shipping']['total'];
        $this->assertSame('30.00', $total['amount']);
        $this->assertSame(3, $total['quantity']);
        $this->assertSame(5, $total['shippable']);
    }

    public function testPaymentsApiLineItemsAreNotShippableOrCancelableForPaidTransaction(): void
    {
        $lineItems = new OrderLineItemCollection([$this->buildShopwareLineItem('shopware-line-1', 2)]);
        $order = $this->buildOrder('pay-xxx', null, OrderTransactionStates::STATE_PAID, $lineItems);

        $repository = new FakeOrderSearchRepository();
        $repository->add($order);

        $gateway = new FakeGateway();
        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway, new OrderAdminStatusBuilder(), new OrderPaymentRecovery($repository));

        $response = $controller->details('order-1', $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $shipping = $body['shipping']['status']['shopware-line-1'];
        $this->assertFalse($shipping['isShippable']);
        $this->assertSame(0, $shipping['shippableQuantity']);

        $cancel = $body['cancelItem']['shopware-line-1'];
        $this->assertFalse($cancel['isCancelable']);
        $this->assertSame(0, $cancel['cancelableQuantity']);
    }

    public function testPaymentsApiLineItemsAreShippableAndCancelableForAuthorizedTransaction(): void
    {
        $lineItems = new OrderLineItemCollection([$this->buildShopwareLineItem('shopware-line-1', 2)]);
        $order = $this->buildOrder('pay-xxx', null, OrderTransactionStates::STATE_AUTHORIZED, $lineItems);

        $repository = new FakeOrderSearchRepository();
        $repository->add($order);

        $gateway = new FakeGateway();
        $controller = new OrderAdminController($repository, new FakeSettingsService(), $gateway, new OrderAdminStatusBuilder(), new OrderPaymentRecovery($repository));

        $response = $controller->details('order-1', $this->context);
        $body = json_decode((string) $response->getContent(), true);

        $shipping = $body['shipping']['status']['shopware-line-1'];
        $this->assertTrue($shipping['isShippable']);
        $this->assertSame(2, $shipping['shippableQuantity']);

        $cancel = $body['cancelItem']['shopware-line-1'];
        $this->assertTrue($cancel['isCancelable']);
        $this->assertSame(2, $cancel['cancelableQuantity']);
    }

    private function buildOrder(string $molliePaymentId, ?string $mollieOrderId, ?string $transactionState = null, ?OrderLineItemCollection $lineItems = null): OrderEntity
    {
        $payment = new Payment($molliePaymentId);
        if ($mollieOrderId !== null) {
            $payment->setOrderId($mollieOrderId);
        }

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->addExtension(Mollie::EXTENSION, $payment);
        if ($transactionState !== null) {
            $state = new StateMachineStateEntity();
            $state->setId('state-1');
            $state->setTechnicalName($transactionState);
            $transaction->setStateMachineState($state);
        }

        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setSalesChannelId('sales-channel-1');
        $order->setTransactions(new OrderTransactionCollection([$transaction]));
        if ($lineItems !== null) {
            $order->setLineItems($lineItems);
        }

        return $order;
    }

    private function buildShopwareLineItem(string $id, int $quantity): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $lineItem->setId($id);
        $lineItem->setQuantity($quantity);
        $lineItem->setCustomFields([]);

        return $lineItem;
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
