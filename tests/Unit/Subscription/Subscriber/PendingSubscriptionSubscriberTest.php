<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Subscriber;

use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusCancelledEvent;
use Mollie\Shopware\Component\FlowBuilder\Event\Webhook\WebhookStatusPaidEvent;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\CancelAction;
use Mollie\Shopware\Component\Subscription\Action\ConfirmAction;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionCollection;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Event\SubscriptionStartedEvent;
use Mollie\Shopware\Component\Subscription\Subscriber\PendingSubscriptionSubscriber;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(PendingSubscriptionSubscriber::class)]
final class PendingSubscriptionSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsBindsPaidAndCancelledWebhooks(): void
    {
        $subscribed = PendingSubscriptionSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(WebhookStatusPaidEvent::class, $subscribed);
        $this->assertArrayHasKey(WebhookStatusCancelledEvent::class, $subscribed);
        $this->assertSame(['onPaidWebhook', PendingSubscriptionSubscriber::PRIORITY], $subscribed[WebhookStatusPaidEvent::class]);
        $this->assertSame(['onCancelledWebhook', PendingSubscriptionSubscriber::PRIORITY], $subscribed[WebhookStatusCancelledEvent::class]);
    }

    public function testOnCancelledWebhookSkipsWhenSubscriptionsAreDisabled(): void
    {
        $repository = new FakeSubscriptionRepository();
        [$subscriber] = $this->buildSubscriber($repository, enabled: false);

        $pending = $this->buildPendingSubscription('subscription-id-1');
        $event = $this->buildCancelledEvent($this->buildOrderWithSubscriptions([$pending]));

        $subscriber->onCancelledWebhook($event);

        $this->assertSame(0, $repository->getUpsertCount());
    }

    public function testOnCancelledWebhookSkipsWhenOrderHasNoSubscriptionExtension(): void
    {
        $repository = new FakeSubscriptionRepository();
        [$subscriber] = $this->buildSubscriber($repository, enabled: true);

        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setSalesChannelId('sales-channel-id');

        $event = $this->buildCancelledEvent($order);
        $subscriber->onCancelledWebhook($event);

        $this->assertSame(0, $repository->getUpsertCount());
    }

    public function testOnCancelledWebhookCancelsPendingSubscription(): void
    {
        $repository = new FakeSubscriptionRepository();
        [$subscriber] = $this->buildSubscriber($repository, enabled: true);

        $pending = $this->buildPendingSubscription('subscription-id-1');
        $event = $this->buildCancelledEvent($this->buildOrderWithSubscriptions([$pending]));

        $subscriber->onCancelledWebhook($event);

        $this->assertSame(1, $repository->getUpsertCount());
        $payload = $repository->getLastUpsert();
        $this->assertSame('subscription-id-1', $payload['id']);
        $this->assertSame(SubscriptionStatus::CANCELED->value, $payload['status']);
        $this->assertSame('canceled', $payload['historyEntries'][0]['comment']);
    }

    public function testOnCancelledWebhookSkipsNonPendingSubscriptions(): void
    {
        $repository = new FakeSubscriptionRepository();
        [$subscriber] = $this->buildSubscriber($repository, enabled: true);

        $active = SubscriptionEntityBuilder::create()
            ->withId('subscription-id-1')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build();
        $event = $this->buildCancelledEvent($this->buildOrderWithSubscriptions([$active]));

        $subscriber->onCancelledWebhook($event);

        $this->assertSame(0, $repository->getUpsertCount());
    }

    public function testOnCancelledWebhookCancelsOnlyPendingAmongMixedSubscriptions(): void
    {
        $repository = new FakeSubscriptionRepository();
        [$subscriber] = $this->buildSubscriber($repository, enabled: true);

        $pending = $this->buildPendingSubscription('subscription-id-pending');
        $active = SubscriptionEntityBuilder::create()
            ->withId('subscription-id-active')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build();
        $event = $this->buildCancelledEvent($this->buildOrderWithSubscriptions([$pending, $active]));

        $subscriber->onCancelledWebhook($event);

        $this->assertSame(1, $repository->getUpsertCount());
        $this->assertSame('subscription-id-pending', $repository->getLastUpsert()['id']);
    }

    public function testOnPaidWebhookSkipsWhenSubscriptionsAreDisabled(): void
    {
        $repository = new FakeSubscriptionRepository();
        $gateway = new FakeSubscriptionGateway();
        [$subscriber] = $this->buildSubscriber($repository, enabled: false, gateway: $gateway);

        $pending = $this->buildPendingSubscription('subscription-id-1');
        $event = $this->buildPaidEvent($this->buildOrderWithCustomer([$pending]), $this->buildPaymentWithMandate());

        $subscriber->onPaidWebhook($event);

        $this->assertSame(0, $repository->getUpsertCount());
        $this->assertSame(0, $gateway->getCallCount('createSubscription'));
    }

    public function testOnPaidWebhookSkipsWhenNoPendingSubscription(): void
    {
        $repository = new FakeSubscriptionRepository();
        $gateway = new FakeSubscriptionGateway();
        [$subscriber] = $this->buildSubscriber($repository, enabled: true, gateway: $gateway);

        $active = SubscriptionEntityBuilder::create()
            ->withId('subscription-id-active')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build();
        $event = $this->buildPaidEvent($this->buildOrderWithCustomer([$active]), $this->buildPaymentWithMandate());

        $subscriber->onPaidWebhook($event);

        $this->assertSame(0, $repository->getUpsertCount());
        $this->assertSame(0, $gateway->getCallCount('createSubscription'));
    }

    public function testOnPaidWebhookConfirmsPendingSubscriptionAndDispatchesStartedEvent(): void
    {
        $repository = new FakeSubscriptionRepository();
        $gateway = new FakeSubscriptionGateway();
        $newMollieSubscription = MollieSubscriptionBuilder::create()
            ->withId('sub_new123')
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->withNextPaymentDate(new \DateTimeImmutable('+30 days'))
            ->build();
        $gateway->setCreateResponse($newMollieSubscription);

        $eventDispatcher = new EventSpy();
        [$subscriber] = $this->buildSubscriber($repository, enabled: true, gateway: $gateway, eventDispatcher: $eventDispatcher);

        $pending = $this->buildPendingSubscription('subscription-id-1');
        $event = $this->buildPaidEvent($this->buildOrderWithCustomer([$pending]), $this->buildPaymentWithMandate());

        $subscriber->onPaidWebhook($event);

        $this->assertSame(1, $gateway->getCallCount('createSubscription'));
        $this->assertSame(1, $repository->getUpsertCount());
        $this->assertSame('confirmed', $repository->getLastUpsert()['historyEntries'][0]['comment']);
        $this->assertInstanceOf(SubscriptionStartedEvent::class, $eventDispatcher->getEvent());
    }

    public function testOnPaidWebhookThrowsWhenPaymentHasNoCustomerId(): void
    {
        $repository = new FakeSubscriptionRepository();
        [$subscriber] = $this->buildSubscriber($repository, enabled: true);

        $pending = $this->buildPendingSubscription('subscription-id-1');
        $event = $this->buildPaidEvent($this->buildOrderWithCustomer([$pending]), new Payment('payment-id'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mollie customer id');

        try {
            $subscriber->onPaidWebhook($event);
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
        }
    }

    public function testOnPaidWebhookThrowsWhenOrderHasNoCustomer(): void
    {
        $repository = new FakeSubscriptionRepository();
        [$subscriber] = $this->buildSubscriber($repository, enabled: true);

        $order = $this->buildOrderWithSubscriptions([$this->buildPendingSubscription('subscription-id-1')]);
        $order->setCurrency($this->buildCurrency('EUR'));

        $event = $this->buildPaidEvent($order, $this->buildPaymentWithMandate());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Customer not loaded for Order');

        try {
            $subscriber->onPaidWebhook($event);
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
        }
    }

    public function testOnPaidWebhookThrowsWhenOrderHasNoCurrency(): void
    {
        $repository = new FakeSubscriptionRepository();
        [$subscriber] = $this->buildSubscriber($repository, enabled: true);

        $pending = $this->buildPendingSubscription('subscription-id-1');
        $order = $this->buildOrderWithSubscriptions([$pending]);
        $this->attachCustomer($order);
        // no currency

        $event = $this->buildPaidEvent($order, $this->buildPaymentWithMandate());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Currency is not set');

        try {
            $subscriber->onPaidWebhook($event);
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
        }
    }

    public function testOnPaidWebhookThrowsWhenPaymentHasNoMandateId(): void
    {
        $repository = new FakeSubscriptionRepository();
        [$subscriber] = $this->buildSubscriber($repository, enabled: true);

        $pending = $this->buildPendingSubscription('subscription-id-1');
        $payment = new Payment('payment-id');
        $payment->setCustomerId('cst_test123');
        // no mandateId

        $event = $this->buildPaidEvent($this->buildOrderWithCustomer([$pending]), $payment);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('mollie mandate id');

        try {
            $subscriber->onPaidWebhook($event);
        } finally {
            $this->assertSame(0, $repository->getUpsertCount());
        }
    }

    /**
     * @return array{0:PendingSubscriptionSubscriber}
     */
    private function buildSubscriber(
        FakeSubscriptionRepository $repository,
        bool $enabled,
        ?FakeSubscriptionGateway $gateway = null,
        ?EventSpy $eventDispatcher = null
    ): array {
        $gateway ??= new FakeSubscriptionGateway();
        $eventDispatcher ??= new EventSpy();
        $settingsService = new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: $enabled));

        $cancelAction = new CancelAction($repository, $gateway, new NullLogger());
        $confirmAction = new ConfirmAction($repository, $gateway, new FakeRouteBuilder(), $eventDispatcher, new NullLogger());

        $subscriber = new PendingSubscriptionSubscriber(
            $settingsService,
            $cancelAction,
            $confirmAction,
            $eventDispatcher,
            new NullLogger()
        );

        return [$subscriber];
    }

    private function buildPendingSubscription(string $id): SubscriptionEntity
    {
        return SubscriptionEntityBuilder::create()
            ->withId($id)
            ->withStatus(SubscriptionStatus::PENDING)
            ->build();
    }

    /**
     * @param list<SubscriptionEntity> $subscriptions
     */
    private function buildOrderWithSubscriptions(array $subscriptions): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId('sales-channel-id');

        $collection = new SubscriptionCollection();
        foreach ($subscriptions as $subscription) {
            $collection->add($subscription);
        }
        $order->addExtension('mollieSubscriptions', $collection);

        return $order;
    }

    /**
     * @param list<SubscriptionEntity> $subscriptions
     */
    private function buildOrderWithCustomer(array $subscriptions): OrderEntity
    {
        $order = $this->buildOrderWithSubscriptions($subscriptions);
        $order->setCurrency($this->buildCurrency('EUR'));
        $this->attachCustomer($order);

        return $order;
    }

    private function attachCustomer(OrderEntity $order): void
    {
        $customer = CustomerBuilder::create()->build();
        $orderCustomer = new OrderCustomerEntity();
        $orderCustomer->setUniqueIdentifier('order-customer-id');
        $orderCustomer->setCustomerId($customer->getId());
        $orderCustomer->setCustomer($customer);
        $order->setOrderCustomer($orderCustomer);
    }

    private function buildPaymentWithMandate(): Payment
    {
        $payment = new Payment('payment-id');
        $payment->setCustomerId('cst_test123');
        $payment->setMandateId('mdt_test123');

        return $payment;
    }

    private function buildCancelledEvent(OrderEntity $order): WebhookStatusCancelledEvent
    {
        return new WebhookStatusCancelledEvent(new Payment('payment-id'), $order, Context::createDefaultContext());
    }

    private function buildPaidEvent(OrderEntity $order, Payment $payment): WebhookStatusPaidEvent
    {
        return new WebhookStatusPaidEvent($payment, $order, Context::createDefaultContext());
    }

    private function buildCurrency(string $iso): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode($iso);

        return $currency;
    }
}
