<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Mollie\IntervalUnit;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\Settings\Struct\SubscriptionSettings;
use Mollie\Shopware\Component\Subscription\Action\UpdatePaymentMethodAction;
use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\Route\UpdatePaymentMethodException;
use Mollie\Shopware\Component\Subscription\Route\UpdatePaymentMethodRoute;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Mollie\Shopware\Component\Subscription\SubscriptionMetadata;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionDataService;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionGateway;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;

#[CoversClass(UpdatePaymentMethodRoute::class)]
final class UpdatePaymentMethodRouteTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const SUBSCRIPTION_ID = 'subscription-id';

    private FakeGateway $mollieGateway;

    private FakeSubscriptionGateway $subscriptionGateway;

    private FakeSubscriptionRepository $subscriptionRepository;

    protected function setUp(): void
    {
        $this->subscriptionGateway = new FakeSubscriptionGateway();
        $this->subscriptionRepository = new FakeSubscriptionRepository();
        $this->mollieGateway = new FakeGateway(payment: $this->approvedPayment());
    }

    public function testTheRouteCannotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->buildRoute()->getDecorated();
    }

    public function testStartIsRejectedWithoutASignedInCustomer(): void
    {
        $route = $this->buildRoute();

        $this->assertStartIsRejected($route, UpdatePaymentMethodException::NOT_AUTHENTICATED, new FakeSalesChannelContext());
    }

    public function testStartIsRejectedForAnotherCustomersSubscription(): void
    {
        $route = $this->buildRoute($this->subscriptionData(customerId: 'someone-else'));

        $this->assertStartIsRejected($route, UpdatePaymentMethodException::SUBSCRIPTION_NOT_OWNED);
    }

    public function testStartIsRejectedWhenSubscriptionsAreDisabled(): void
    {
        $route = $this->buildRoute(settings: new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: false)));

        $this->assertStartIsRejected($route, UpdatePaymentMethodException::SUBSCRIPTIONS_DISABLED);
    }

    public function testStartIsRejectedForASubscriptionThatIsNoLongerActive(): void
    {
        $route = $this->buildRoute($this->subscriptionData(status: SubscriptionStatus::CANCELED));

        $this->assertStartIsRejected($route, UpdatePaymentMethodException::PAYMENT_UPDATE_NOT_ALLOWED);
    }

    public function testStartAnswersWithTheMollieCheckoutUrl(): void
    {
        $payment = new Payment('tr_new');
        $payment->setCheckoutUrl('https://mollie.test/checkout');
        $this->mollieGateway = new FakeGateway(payment: $payment);

        $route = $this->buildRoute();

        $response = $route->start(self::SUBSCRIPTION_ID, new RequestDataBag(), $this->authenticatedContext());

        self::assertSame([
            'success' => true,
            'subscriptionId' => self::SUBSCRIPTION_ID,
            'checkoutUrl' => 'https://mollie.test/checkout',
        ], $response->getObject()->all());
    }

    public function testStartPassesTheRequestedRedirectUrlToMollie(): void
    {
        $route = $this->buildRoute();

        $route->start(
            self::SUBSCRIPTION_ID,
            new RequestDataBag(['redirectUrl' => 'https://shop.test/account/subscriptions']),
            $this->authenticatedContext()
        );

        self::assertSame('https://shop.test/account/subscriptions', $this->mollieGateway->getCreatePayloads()[0]->getRedirectUrl());
    }

    public function testTheSubscriptionIdFromTheUrlIsLoweredBeforeTheLookup(): void
    {
        $dataService = new FakeSubscriptionDataService($this->subscriptionData());
        $route = $this->buildRoute(dataService: $dataService);

        $route->start('SUBSCRIPTION-ID', new RequestDataBag(), $this->authenticatedContext());

        self::assertSame([['subscriptionId' => self::SUBSCRIPTION_ID]], $dataService->getCalls());
    }

    public function testConfirmIsRejectedWithoutASignedInCustomer(): void
    {
        $route = $this->buildRoute();

        $this->assertConfirmIsRejected($route, UpdatePaymentMethodException::NOT_AUTHENTICATED, new FakeSalesChannelContext());
    }

    public function testConfirmIsRejectedForAnotherCustomersSubscription(): void
    {
        $route = $this->buildRoute($this->subscriptionData(customerId: 'someone-else'));

        $this->assertConfirmIsRejected($route, UpdatePaymentMethodException::SUBSCRIPTION_NOT_OWNED);
    }

    #[DataProvider('statusesThatCannotConfirm')]
    public function testConfirmIsRejectedForASubscriptionThatIsNotRunning(SubscriptionStatus $status): void
    {
        $route = $this->buildRoute($this->subscriptionData(status: $status));

        $this->assertConfirmIsRejected($route, UpdatePaymentMethodException::SUBSCRIPTION_NOT_ACTIVE);
    }

    /**
     * @return array<string, array{SubscriptionStatus}>
     */
    public static function statusesThatCannotConfirm(): array
    {
        return [
            'cancelled' => [SubscriptionStatus::CANCELED],
            'paused' => [SubscriptionStatus::PAUSED],
            'pending' => [SubscriptionStatus::PENDING],
        ];
    }

    #[DataProvider('statusesThatCanConfirm')]
    public function testConfirmAnswersWithTheSubscriptionId(SubscriptionStatus $status): void
    {
        $route = $this->buildRoute($this->subscriptionData(status: $status));

        $response = $route->confirm(self::SUBSCRIPTION_ID, $this->authenticatedContext());

        self::assertSame([
            'success' => true,
            'subscriptionId' => self::SUBSCRIPTION_ID,
        ], $response->getObject()->all());
    }

    /**
     * @return array<string, array{SubscriptionStatus}>
     */
    public static function statusesThatCanConfirm(): array
    {
        return [
            'active' => [SubscriptionStatus::ACTIVE],
            'resumed' => [SubscriptionStatus::RESUMED],
        ];
    }

    public function testConfirmStoresTheNewMandateOnTheSubscription(): void
    {
        $route = $this->buildRoute();

        $route->confirm(self::SUBSCRIPTION_ID, $this->authenticatedContext());

        self::assertSame('mdt_new', $this->subscriptionRepository->getLastUpsert()['mandateId']);
    }

    private function assertStartIsRejected(UpdatePaymentMethodRoute $route, string $errorCode, ?FakeSalesChannelContext $context = null): void
    {
        try {
            $route->start(self::SUBSCRIPTION_ID, new RequestDataBag(), $context ?? $this->authenticatedContext());
        } catch (UpdatePaymentMethodException $exception) {
            self::assertSame($errorCode, $exception->getErrorCode());

            return;
        }

        self::fail(sprintf('Expected the payment method update to be rejected with %s', $errorCode));
    }

    private function assertConfirmIsRejected(UpdatePaymentMethodRoute $route, string $errorCode, ?FakeSalesChannelContext $context = null): void
    {
        try {
            $route->confirm(self::SUBSCRIPTION_ID, $context ?? $this->authenticatedContext());
        } catch (UpdatePaymentMethodException $exception) {
            self::assertSame($errorCode, $exception->getErrorCode());

            return;
        }

        self::fail(sprintf('Expected the confirmation to be rejected with %s', $errorCode));
    }

    private function buildRoute(
        ?SubscriptionDataStruct $subscriptionData = null,
        ?FakeSettingsService $settings = null,
        ?FakeSubscriptionDataService $dataService = null,
    ): UpdatePaymentMethodRoute {
        $subscriptionData ??= $this->subscriptionData();
        $this->subscriptionGateway->register(MollieSubscriptionBuilder::create()->withId('sub_test123')->build());

        $action = new UpdatePaymentMethodAction(
            $this->subscriptionRepository,
            $this->subscriptionGateway,
            $this->mollieGateway,
            new FakeRouteBuilder(subscriptionPaymentUpdateReturnUrl: 'https://shop.test/return'),
            new PaymentHandlerLocator([]),
            new NullLogger()
        );

        return new UpdatePaymentMethodRoute(
            $settings ?? new FakeSettingsService(subscriptionSettings: new SubscriptionSettings(enabled: true)),
            $dataService ?? new FakeSubscriptionDataService($subscriptionData),
            $action
        );
    }

    private function subscriptionData(
        string $customerId = self::CUSTOMER_ID,
        SubscriptionStatus $status = SubscriptionStatus::ACTIVE,
    ): SubscriptionDataStruct {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withCustomerId($customerId)
            ->withMollieId('sub_test123')
            ->withStatus($status)
            ->withMetadata(new SubscriptionMetadata('2026-06-01', 1, IntervalUnit::MONTHS, 0, 'tr_tmp'))
            ->build()
        ;

        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('10000');

        return $this->subscriptionDataStruct($subscription, $order);
    }

    private function subscriptionDataStruct(SubscriptionEntity $subscription, OrderEntity $order): SubscriptionDataStruct
    {
        $billingAddress = $subscription->getBillingAddress();
        $shippingAddress = $subscription->getShippingAddress();
        self::assertNotNull($billingAddress);
        self::assertNotNull($shippingAddress);

        return new SubscriptionDataStruct(
            $subscription,
            $order,
            CustomerBuilder::create()->build(),
            $billingAddress,
            $shippingAddress
        );
    }

    private function approvedPayment(): Payment
    {
        $payment = new Payment('tr_tmp');
        $payment->setStatus(PaymentStatus::PAID);
        $payment->setMandateId('mdt_new');
        $payment->setCheckoutUrl('https://mollie.test/checkout');

        return $payment;
    }

    private function authenticatedContext(string $customerId = self::CUSTOMER_ID): FakeSalesChannelContext
    {
        $customer = new CustomerEntity();
        $customer->setId($customerId);

        $context = new FakeSalesChannelContext();
        $context->setCustomer($customer);

        return $context;
    }
}
