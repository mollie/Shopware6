<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Order\Admin;

use Mollie\Shopware\Component\Mollie\LineItem;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Order\Admin\OrderAdminController;
use Mollie\Shopware\Component\Mollie\Payment;
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
}
