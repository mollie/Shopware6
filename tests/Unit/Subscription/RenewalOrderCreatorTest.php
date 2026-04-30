<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Subscription\RenewalAddresses;
use Mollie\Shopware\Component\Subscription\RenewalOrderCreator;
use Mollie\Shopware\Component\Subscription\Route\RenewException;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCart;
use Mollie\Shopware\Component\Subscription\SubscriptionTag;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Subscription\Fake\FakeCartOrderRoute;
use Mollie\Shopware\Unit\Subscription\Fake\FakeOrderEntityRepository;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGroupCartBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

#[CoversClass(RenewalOrderCreator::class)]
final class RenewalOrderCreatorTest extends TestCase
{
    public function testCreateThrowsRenewExceptionWhenGroupCartCannotBeBuilt(): void
    {
        $groupCartBuilder = new FakeSubscriptionGroupCartBuilder(null);
        $creator = new RenewalOrderCreator(
            $groupCartBuilder,
            new FakeCartOrderRoute(),
            new FakeOrderEntityRepository(),
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
            new FakeOrderEntityRepository(),
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

        $orderRepository = new FakeOrderEntityRepository();
        $creator = new RenewalOrderCreator($groupCartBuilder, $cartOrderRoute, $orderRepository, new NullLogger());

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
            new FakeOrderEntityRepository(),
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
            new FakeOrderEntityRepository(),
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
}
