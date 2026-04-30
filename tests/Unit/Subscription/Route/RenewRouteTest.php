<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\EnvironmentSettings;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\RenewAction;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\Aggregate\SubscriptionAddress\SubscriptionAddressEntity;
use Mollie\Shopware\Component\Subscription\RenewalOrderCreator;
use Mollie\Shopware\Component\Subscription\Route\RenewException;
use Mollie\Shopware\Component\Subscription\Route\RenewRoute;
use Mollie\Shopware\Component\Subscription\Route\WebhookException;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCart;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionAddressBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeCartOrderRoute;
use Mollie\Shopware\Unit\Subscription\Fake\FakeOrderEntityRepository;
use Mollie\Shopware\Unit\Subscription\Fake\FakePaymentWebhookRoute;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionActionHandler;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionAddressSyncer;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionDataService;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGroupCartBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(RenewRoute::class)]
final class RenewRouteTest extends TestCase
{
    public function testRenewThrowsWhenPaymentIdMissing(): void
    {
        $route = $this->buildRoute();

        $this->expectException(WebhookException::class);

        $route->renew('subscription-id', new Request(), Context::createDefaultContext());
    }

    public function testRenewThrowsWhenSubscriptionsAreDisabled(): void
    {
        $settings = new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: false));
        $route = $this->buildRoute(settings: $settings);

        $this->expectException(RenewException::class);

        $route->renew('subscription-id', new Request(query: ['id' => 'mollie-payment-id']), Context::createDefaultContext());
    }

    public function testRenewThrowsOnInvalidPaymentIdInProductionMode(): void
    {
        $payment = $this->buildPayment();
        $payment->setSubscriptionId('different-mollie-subscription-id');

        $route = $this->buildRoute(payment: $payment);

        $this->expectException(RenewException::class);

        $route->renew('subscription-id', new Request(query: ['id' => 'mollie-payment-id']), Context::createDefaultContext());
    }

    public function testRenewSkipsCreationWhenPaymentFailedAndSkipIfFailedEnabled(): void
    {
        $payment = $this->buildPayment();
        $payment->setStatus(PaymentStatus::FAILED);

        $orderRepository = new FakeOrderEntityRepository();
        $subscriptionRepository = new FakeSubscriptionRepository();
        $paymentWebhookRoute = new FakePaymentWebhookRoute();

        $route = $this->buildRoute(
            settings: new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: true, skipIfFailed: true)),
            payment: $payment,
            orderRepository: $orderRepository,
            subscriptionRepository: $subscriptionRepository,
            paymentWebhookRoute: $paymentWebhookRoute,
        );

        $response = $route->renew('subscription-id', new Request(query: ['id' => 'mollie-payment-id']), Context::createDefaultContext());

        $this->assertSame($payment, $response->getPayment());
        $this->assertSame(0, $orderRepository->getUpsertCount());
        $this->assertSame(0, $subscriptionRepository->getUpsertCount());
        $this->assertSame(0, $paymentWebhookRoute->getCallCount());
    }

    public function testRenewBypassesPaymentValidationInDevMode(): void
    {
        $payment = $this->buildPayment();
        $payment->setSubscriptionId('different-mollie-subscription-id');

        $settings = new FakeSettingsService(
            subscriptionSettings: new SubscriptionSettings(enabled: true),
            environmentSettings: new EnvironmentSettings(true, false),
        );
        $paymentWebhookRoute = new FakePaymentWebhookRoute();

        $route = $this->buildRoute(payment: $payment, settings: $settings, paymentWebhookRoute: $paymentWebhookRoute);

        $route->renew('subscription-id', new Request(query: ['id' => 'mollie-payment-id']), Context::createDefaultContext());

        $this->assertSame(1, $paymentWebhookRoute->getCallCount());
    }

    public function testRenewLowercasesSubscriptionIdBeforeLoadingData(): void
    {
        $dataService = new FakeSubscriptionDataService($this->buildSubscriptionData());
        $route = $this->buildRoute(dataService: $dataService);

        $route->renew('SUBSCRIPTION-ID', new Request(query: ['id' => 'mollie-payment-id']), Context::createDefaultContext());

        $this->assertSame('subscription-id', $dataService->getCalls()[0]['subscriptionId']);
    }

    public function testRenewDelegatesToOrderCreatorAndPersistsRenewal(): void
    {
        $orderRepository = new FakeOrderEntityRepository();
        $subscriptionRepository = new FakeSubscriptionRepository();
        $paymentWebhookRoute = new FakePaymentWebhookRoute();

        $route = $this->buildRoute(
            orderRepository: $orderRepository,
            subscriptionRepository: $subscriptionRepository,
            paymentWebhookRoute: $paymentWebhookRoute,
        );

        $route->renew('subscription-id', new Request(query: ['id' => 'mollie-payment-id']), Context::createDefaultContext());

        $this->assertSame(1, $orderRepository->getUpsertCount());
        $this->assertSame(1, $subscriptionRepository->getUpsertCount());
        $this->assertSame('renewed', $subscriptionRepository->getLastUpsert()['historyEntries'][0]['comment']);
        $this->assertSame(1, $paymentWebhookRoute->getCallCount());
        $this->assertSame('new-transaction-id', $paymentWebhookRoute->getCalls()[0]['transactionId']);
    }

    private function buildRoute(
        ?FakeSettingsService $settings = null,
        ?FakeSubscriptionDataService $dataService = null,
        ?Payment $payment = null,
        ?FakeOrderEntityRepository $orderRepository = null,
        ?FakeSubscriptionRepository $subscriptionRepository = null,
        ?FakePaymentWebhookRoute $paymentWebhookRoute = null,
    ): RenewRoute {
        $settings ??= new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: true));
        $dataService ??= new FakeSubscriptionDataService($this->buildSubscriptionData());
        $payment ??= $this->buildPayment();
        $orderRepository ??= new FakeOrderEntityRepository();
        $subscriptionRepository ??= new FakeSubscriptionRepository();
        $paymentWebhookRoute ??= new FakePaymentWebhookRoute();

        $subscriptionGateway = new FakeSubscriptionGateway();
        $mollieSubscription = MollieSubscriptionBuilder::create()
            ->withId('sub_test123')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate(new \DateTimeImmutable('2099-06-01'))
            ->build();
        $subscriptionGateway->register($mollieSubscription);

        $mollieGateway = new FakeGateway(payment: $payment);

        $newTransaction = new OrderTransactionEntity();
        $newTransaction->setId('new-transaction-id');
        $newOrder = new OrderEntity();
        $newOrder->setId('new-order-id');
        $newOrder->setOrderNumber('20000');
        $newOrder->setTransactions(new OrderTransactionCollection([$newTransaction]));

        $cartOrderRoute = new FakeCartOrderRoute();
        $cartOrderRoute->setResponse($newOrder);

        $groupCart = new SubscriptionGroupCart(new Cart('cart-token'), new FakeSalesChannelContext());
        $groupCartBuilder = new FakeSubscriptionGroupCartBuilder($groupCart);

        $renewalOrderCreator = new RenewalOrderCreator(
            $groupCartBuilder,
            $cartOrderRoute,
            $orderRepository,
            new NullLogger()
        );

        $renewAction = new RenewAction(
            $subscriptionRepository,
            new EventSpy(),
            new FakeSubscriptionActionHandler(),
            new NullLogger()
        );

        return new RenewRoute(
            $settings,
            $dataService,
            $subscriptionGateway,
            $mollieGateway,
            new FakeSubscriptionAddressSyncer(),
            $renewalOrderCreator,
            $renewAction,
            $paymentWebhookRoute,
            new NullLogger()
        );
    }

    private function buildPayment(): Payment
    {
        $payment = new Payment('mollie-payment-id');
        $payment->setStatus(PaymentStatus::PAID);
        $payment->setSubscriptionId('sub_test123');

        return $payment;
    }

    private function buildSubscriptionData(): SubscriptionDataStruct
    {
        $billingAddress = SubscriptionAddressBuilder::create()->withFirstName('Bill')->build();
        $shippingAddress = SubscriptionAddressBuilder::create()->withFirstName('Ship')->build();

        $subscription = SubscriptionEntityBuilder::create()
            ->withId('subscription-id')
            ->withMollieId('sub_test123')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withMetadata(new SubscriptionMetadata('2026-06-01', 1, IntervalUnit::MONTHS, 0))
            ->withBillingAddress($billingAddress)
            ->withShippingAddress($shippingAddress)
            ->build();

        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('10000');

        return new SubscriptionDataStruct(
            $subscription,
            $order,
            CustomerBuilder::create()->build(),
            $billingAddress,
            $shippingAddress
        );
    }
}
