<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\RenewalAddresses;
use Mollie\Shopware\Component\Subscription\RenewalOrderCreator;
use Mollie\Shopware\Component\Subscription\Route\RenewException;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCart;
use Mollie\Shopware\Component\Subscription\SubscriptionTag;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeOrderRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeCartOrderRoute;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGroupCartBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[CoversClass(RenewalOrderCreator::class)]
final class RenewalOrderCreatorTest extends TestCase
{
    public function testCreateThrowsRenewExceptionWhenGroupCartCannotBeBuilt(): void
    {
        $groupCartBuilder = new FakeSubscriptionGroupCartBuilder(null);
        $creator = new RenewalOrderCreator(
            $groupCartBuilder,
            new FakeCartOrderRoute(),
            new FakeOrderRepository(),
            new NullLogger()
        );

        $this->expectException(RenewException::class);

        $creator->create(
            new OrderEntity(),
            'subscription-id',
            '1 months',
            new RenewalAddresses('billing-id', 'shipping-id'),
            new Payment('payment-id'),
            Context::createDefaultContext()
        );
    }

    public function testCreateThrowsRenewExceptionWhenNewOrderHasNoTransaction(): void
    {
        $groupCart = new SubscriptionGroupCart(new Cart('cart-token'), new FakeSalesChannelContext());
        $groupCartBuilder = new FakeSubscriptionGroupCartBuilder($groupCart);

        $cartOrderRoute = new FakeCartOrderRoute();
        $newOrder = new OrderEntity();
        $newOrder->setId('new-order-id');
        $newOrder->setOrderNumber('20000');
        $newOrder->setTransactions(new OrderTransactionCollection());
        $cartOrderRoute->setResponse($newOrder);

        $creator = new RenewalOrderCreator(
            $groupCartBuilder,
            $cartOrderRoute,
            new FakeOrderRepository(),
            new NullLogger()
        );

        $this->expectException(RenewException::class);

        $creator->create(
            new OrderEntity(),
            'subscription-id',
            '1 months',
            new RenewalAddresses('billing-id', 'shipping-id'),
            new Payment('payment-id'),
            Context::createDefaultContext()
        );
    }

    public function testCreateUpsertsSubscriptionTagAndTransactionCustomFields(): void
    {
        $groupCart = new SubscriptionGroupCart(new Cart('cart-token'), new FakeSalesChannelContext());
        $groupCartBuilder = new FakeSubscriptionGroupCartBuilder($groupCart);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('new-transaction-id');

        $newOrder = new OrderEntity();
        $newOrder->setId('new-order-id');
        $newOrder->setOrderNumber('20000');
        $newOrder->setTransactions(new OrderTransactionCollection([$transaction]));

        $cartOrderRoute = new FakeCartOrderRoute();
        $cartOrderRoute->setResponse($newOrder);

        $orderRepository = new FakeOrderRepository();
        $creator = new RenewalOrderCreator(
            $groupCartBuilder,
            $cartOrderRoute,
            $orderRepository,
            new NullLogger()
        );

        $payment = new Payment('payment-id');

        $resultTransaction = $creator->create(
            new OrderEntity(),
            'subscription-id',
            '1 months',
            new RenewalAddresses('billing-id', 'shipping-id'),
            $payment,
            Context::createDefaultContext()
        );

        $this->assertSame($transaction, $resultTransaction);
        $this->assertSame(1, $orderRepository->getUpsertCount());

        $payload = $orderRepository->getLastUpsert();
        $this->assertSame('new-order-id', $payload['id']);
        $this->assertSame(SubscriptionTag::ID, $payload['tags'][0]['id']);
        $this->assertSame('new-transaction-id', $payload['transactions'][0]['id']);
        $this->assertArrayHasKey(Mollie::EXTENSION, $payload['transactions'][0]['customFields']);
    }

    public function testCreatePassesAddressesToGroupCartBuilder(): void
    {
        $groupCart = new SubscriptionGroupCart(new Cart('cart-token'), new FakeSalesChannelContext());
        $groupCartBuilder = new FakeSubscriptionGroupCartBuilder($groupCart);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('new-transaction-id');

        $newOrder = new OrderEntity();
        $newOrder->setId('new-order-id');
        $newOrder->setOrderNumber('20000');
        $newOrder->setTransactions(new OrderTransactionCollection([$transaction]));

        $cartOrderRoute = new FakeCartOrderRoute();
        $cartOrderRoute->setResponse($newOrder);

        $creator = new RenewalOrderCreator(
            $groupCartBuilder,
            $cartOrderRoute,
            new FakeOrderRepository(),
            new NullLogger()
        );

        $addresses = new RenewalAddresses('billing-id', 'shipping-id');
        $creator->create(
            new OrderEntity(),
            'subscription-id',
            '2 weeks',
            $addresses,
            new Payment('payment-id'),
            Context::createDefaultContext()
        );

        $this->assertSame(1, $groupCartBuilder->getCallCount());
        $call = $groupCartBuilder->getCalls()[0];
        $this->assertSame('2 weeks', $call['intervalKey']);
        $this->assertSame($addresses, $call['addresses']);
    }

    public function testCreateAttachesTransactionToPayment(): void
    {
        $groupCart = new SubscriptionGroupCart(new Cart('cart-token'), new FakeSalesChannelContext());
        $groupCartBuilder = new FakeSubscriptionGroupCartBuilder($groupCart);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('new-transaction-id');

        $newOrder = new OrderEntity();
        $newOrder->setId('new-order-id');
        $newOrder->setOrderNumber('20000');
        $newOrder->setTransactions(new OrderTransactionCollection([$transaction]));

        $cartOrderRoute = new FakeCartOrderRoute();
        $cartOrderRoute->setResponse($newOrder);

        $creator = new RenewalOrderCreator(
            $groupCartBuilder,
            $cartOrderRoute,
            new FakeOrderRepository(),
            new NullLogger()
        );

        $payment = new Payment('payment-id');
        $creator->create(
            new OrderEntity(),
            'subscription-id',
            '1 months',
            new RenewalAddresses('billing-id', 'shipping-id'),
            $payment,
            Context::createDefaultContext()
        );

        $this->assertSame($transaction, $payment->getShopwareTransaction());
    }

    public function testLinkExistingOrderThrowsWhenOrderIdIsNotAUuid(): void
    {
        $creator = $this->buildCreator(new FakeOrderRepository());

        $errorCode = $this->captureLinkError($creator, '10000', SubscriptionEntityBuilder::create()->build());

        $this->assertSame(RenewException::ORDER_NOT_FOUND, $errorCode);
    }

    public function testLinkExistingOrderThrowsWhenOrderBelongsToAnotherCustomer(): void
    {
        $orderId = Uuid::randomHex();
        $orderRepository = new FakeOrderRepository();
        $orderRepository->add($this->buildExistingOrder($orderId, 'someone-else', 'sales-channel-id'));

        $creator = $this->buildCreator($orderRepository);

        $errorCode = $this->captureLinkError($creator, $orderId, SubscriptionEntityBuilder::create()->build());

        $this->assertSame(RenewException::ORDER_NOT_FOUND, $errorCode);
    }

    public function testLinkExistingOrderThrowsWhenOrderBelongsToAnotherSalesChannel(): void
    {
        $orderId = Uuid::randomHex();
        $orderRepository = new FakeOrderRepository();
        $orderRepository->add($this->buildExistingOrder($orderId, 'customer-id', 'another-sales-channel-id'));

        $creator = $this->buildCreator($orderRepository);

        $errorCode = $this->captureLinkError($creator, $orderId, SubscriptionEntityBuilder::create()->build());

        $this->assertSame(RenewException::ORDER_NOT_FOUND, $errorCode);
    }

    public function testLinkExistingOrderThrowsWhenOrderHasNoTransaction(): void
    {
        $orderId = Uuid::randomHex();
        $order = $this->buildExistingOrder($orderId, 'customer-id', 'sales-channel-id');
        $order->setTransactions(new OrderTransactionCollection());

        $orderRepository = new FakeOrderRepository();
        $orderRepository->add($order);

        $creator = $this->buildCreator($orderRepository);

        $errorCode = $this->captureLinkError($creator, $orderId, SubscriptionEntityBuilder::create()->build());

        $this->assertSame(RenewException::EXISTING_ORDER_WITHOUT_TRANSACTION, $errorCode);
    }

    public function testLinkExistingOrderThrowsWhenTransactionAlreadyHasAMolliePayment(): void
    {
        $orderId = Uuid::randomHex();
        $order = $this->buildExistingOrder($orderId, 'customer-id', 'sales-channel-id');
        $order->getTransactions()?->first()?->setCustomFields([Mollie::EXTENSION => ['id' => 'other-payment-id']]);

        $orderRepository = new FakeOrderRepository();
        $orderRepository->add($order);

        $creator = $this->buildCreator($orderRepository);

        $errorCode = $this->captureLinkError($creator, $orderId, SubscriptionEntityBuilder::create()->build());

        $this->assertSame(RenewException::ORDER_ALREADY_LINKED, $errorCode);
    }

    public function testLinkExistingOrderUpsertsSubscriptionTagAndTransactionCustomFields(): void
    {
        $orderId = Uuid::randomHex();
        $orderRepository = new FakeOrderRepository();
        $orderRepository->add($this->buildExistingOrder($orderId, 'customer-id', 'sales-channel-id'));

        $creator = $this->buildCreator($orderRepository);
        $payment = new Payment('payment-id');

        $transaction = $creator->linkExistingOrder(
            $orderId,
            SubscriptionEntityBuilder::create()->build(),
            $payment,
            Context::createDefaultContext()
        );

        $this->assertSame('existing-transaction-id', $transaction->getId());
        $this->assertSame($transaction, $payment->getShopwareTransaction());

        $payload = $orderRepository->getLastUpsert();
        $this->assertSame($orderId, $payload['id']);
        $this->assertSame(SubscriptionTag::ID, $payload['tags'][0]['id']);
        $this->assertSame('existing-transaction-id', $payload['transactions'][0]['id']);
        $this->assertSame('payment-id', $payload['transactions'][0]['customFields'][Mollie::EXTENSION]['id']);
    }

    public function testLinkExistingOrderSkipsACancelledTransaction(): void
    {
        $orderId = Uuid::randomHex();
        $order = $this->buildExistingOrder($orderId, 'customer-id', 'sales-channel-id');
        $order->setTransactions(new OrderTransactionCollection([
            $this->buildTransaction('cancelled-transaction-id', OrderTransactionStates::STATE_CANCELLED, '2026-01-01'),
            $this->buildTransaction('paid-transaction-id', OrderTransactionStates::STATE_PAID, '2026-01-02'),
        ]));

        $orderRepository = new FakeOrderRepository();
        $orderRepository->add($order);

        $creator = $this->buildCreator($orderRepository);

        $transaction = $creator->linkExistingOrder(
            $orderId,
            SubscriptionEntityBuilder::create()->build(),
            new Payment('payment-id'),
            Context::createDefaultContext()
        );

        $this->assertSame('paid-transaction-id', $transaction->getId());
    }

    private function captureLinkError(RenewalOrderCreator $creator, string $orderId, SubscriptionEntity $subscription): string
    {
        try {
            $creator->linkExistingOrder($orderId, $subscription, new Payment('payment-id'), Context::createDefaultContext());
        } catch (RenewException $exception) {
            return (string) $exception->getErrorCode();
        }

        $this->fail('linkExistingOrder did not throw a RenewException');
    }

    private function buildCreator(FakeOrderRepository $orderRepository): RenewalOrderCreator
    {
        return new RenewalOrderCreator(
            new FakeSubscriptionGroupCartBuilder(null),
            new FakeCartOrderRoute(),
            $orderRepository,
            new NullLogger()
        );
    }

    private function buildExistingOrder(string $orderId, string $customerId, string $salesChannelId): OrderEntity
    {
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setId('order-customer-id');
        $orderCustomer->setCustomerId($customerId);

        $order = new OrderEntity();
        $order->setId($orderId);
        $order->setOrderNumber('10042');
        $order->setSalesChannelId($salesChannelId);
        $order->setOrderCustomer($orderCustomer);
        $order->setTransactions(new OrderTransactionCollection([$this->buildTransaction('existing-transaction-id')]));

        return $order;
    }

    private function buildTransaction(string $id, ?string $state = null, string $createdAt = '2026-01-01'): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId($id);
        $transaction->setCreatedAt(new \DateTimeImmutable($createdAt));

        if ($state !== null) {
            $stateMachineState = new StateMachineStateEntity();
            $stateMachineState->setId($id . '-state');
            $stateMachineState->setTechnicalName($state);
            $transaction->setStateMachineState($stateMachineState);
        }

        return $transaction;
    }
}
