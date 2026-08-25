<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Route;

use Mollie\Shopware\Component\Mollie\SubscriptionStatus;
use Mollie\Shopware\Component\Subscription\Route\ChangeStateException;
use Mollie\Shopware\Component\Subscription\Route\ChangeStateRoute;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Builder\SubscriptionEntityBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionActionHandler;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionDataService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ChangeStateRoute::class)]
final class ChangeStateRouteTest extends TestCase
{
    private const CUSTOMER_ID = 'customer-id';

    private const SUBSCRIPTION_ID = 'subscription-id';

    private FakeSubscriptionActionHandler $actionHandler;

    protected function setUp(): void
    {
        $this->actionHandler = new FakeSubscriptionActionHandler();
        $this->actionHandler->setResponse(MollieSubscriptionBuilder::create()->withId('sub_test123')->build());
    }

    public function testTheRouteCannotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->buildRoute()->getDecorated();
    }

    public function testTheChangeIsRejectedWithoutASignedInCustomer(): void
    {
        $route = $this->buildRoute();

        try {
            $route->changeState(self::SUBSCRIPTION_ID, $this->actionRequest('pause'), new FakeSalesChannelContext());
        } catch (ChangeStateException $exception) {
            static::assertSame(ChangeStateException::NOT_AUTHENTICATED, $exception->getErrorCode());

            return;
        }

        static::fail('Expected the state change to be rejected without a customer');
    }

    public function testTheChangeIsRejectedForAnotherCustomersSubscription(): void
    {
        $route = $this->buildRoute($this->subscriptionData(customerId: 'someone-else'));

        try {
            $route->changeState(self::SUBSCRIPTION_ID, $this->actionRequest('cancel'), $this->authenticatedContext());
        } catch (ChangeStateException $exception) {
            static::assertSame(ChangeStateException::SUBSCRIPTION_NOT_OWNED, $exception->getErrorCode());

            return;
        }

        static::fail('Expected the state change to be rejected for a foreign subscription');
    }

    public function testNoActionIsRunForAnotherCustomersSubscription(): void
    {
        $route = $this->buildRoute($this->subscriptionData(customerId: 'someone-else'));

        try {
            $route->changeState(self::SUBSCRIPTION_ID, $this->actionRequest('cancel'), $this->authenticatedContext());
        } catch (ChangeStateException) {
            // asserted in its own test
        }

        static::assertSame(0, $this->actionHandler->getCallCount());
    }

    #[DataProvider('storefrontActions')]
    public function testTheRequestedActionIsHandedToTheActionHandler(string $action): void
    {
        $route = $this->buildRoute();

        $route->changeState(self::SUBSCRIPTION_ID, $this->actionRequest($action), $this->authenticatedContext());

        static::assertSame([['action' => $action, 'subscriptionId' => self::SUBSCRIPTION_ID]], $this->actionHandler->getCalls());
    }

    #[DataProvider('storefrontActions')]
    public function testTheResponseNamesTheSubscriptionAndTheAppliedAction(string $action): void
    {
        $route = $this->buildRoute();

        $response = $route->changeState(self::SUBSCRIPTION_ID, $this->actionRequest($action), $this->authenticatedContext());

        static::assertSame([
            'success' => true,
            'subscriptionId' => self::SUBSCRIPTION_ID,
            'action' => $action,
        ], $response->getObject()->all());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function storefrontActions(): array
    {
        return [
            'pause' => ['pause'],
            'resume' => ['resume'],
            'skip' => ['skip'],
            'cancel' => ['cancel'],
        ];
    }

    public function testTheSubscriptionIdFromTheUrlIsLoweredBeforeTheLookup(): void
    {
        $dataService = new FakeSubscriptionDataService($this->subscriptionData());
        $route = $this->buildRoute(dataService: $dataService);

        $route->changeState('SUBSCRIPTION-ID', $this->actionRequest('pause'), $this->authenticatedContext());

        static::assertSame([['subscriptionId' => self::SUBSCRIPTION_ID]], $dataService->getCalls());
    }

    private function buildRoute(
        ?SubscriptionDataStruct $subscriptionData = null,
        ?FakeSubscriptionDataService $dataService = null,
    ): ChangeStateRoute {
        $subscriptionData ??= $this->subscriptionData();

        return new ChangeStateRoute(
            $dataService ?? new FakeSubscriptionDataService($subscriptionData),
            $this->actionHandler
        );
    }

    private function subscriptionData(string $customerId = self::CUSTOMER_ID): SubscriptionDataStruct
    {
        $subscription = SubscriptionEntityBuilder::create()
            ->withId(self::SUBSCRIPTION_ID)
            ->withCustomerId($customerId)
            ->withStatus(SubscriptionStatus::ACTIVE)
            ->build()
        ;

        $billingAddress = $subscription->getBillingAddress();
        $shippingAddress = $subscription->getShippingAddress();
        static::assertNotNull($billingAddress);
        static::assertNotNull($shippingAddress);

        $order = new OrderEntity();
        $order->setId('order-id');

        return new SubscriptionDataStruct(
            $subscription,
            $order,
            CustomerBuilder::create()->build(),
            $billingAddress,
            $shippingAddress
        );
    }

    private function actionRequest(string $action): Request
    {
        $request = new Request();
        $request->attributes->set('action', $action);

        return $request;
    }

    private function authenticatedContext(): FakeSalesChannelContext
    {
        $customer = new CustomerEntity();
        $customer->setId(self::CUSTOMER_ID);

        $context = new FakeSalesChannelContext();
        $context->setCustomer($customer);

        return $context;
    }
}
