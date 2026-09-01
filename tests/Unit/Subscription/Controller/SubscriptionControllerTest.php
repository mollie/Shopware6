<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Controller;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Mollie\Shopware\Component\Subscription\Controller\SubscriptionController;
use Mollie\Shopware\Unit\Builder\CustomerBuilder;
use Mollie\Shopware\Unit\Fake\FakeRouter;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Subscription\Builder\MollieSubscriptionBuilder;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionActionHandler;
use Mollie\Shopware\Unit\Subscription\Fake\FakeSubscriptionPageLoader;
use Mollie\Shopware\Unit\Subscription\Fake\FakeUpdateAddressRoute;
use Mollie\Shopware\Unit\Subscription\Fake\FakeUpdatePaymentMethodRoute;
use Mollie\Shopware\Unit\Subscription\Fake\FakeWebhookRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Translation\IdentityTranslator;

#[CoversClass(SubscriptionController::class)]
final class SubscriptionControllerTest extends TestCase
{
    private FakeRouter $router;

    private Session $session;

    protected function setUp(): void
    {
        $this->router = new FakeRouter('https://shop.test/target');
        $this->session = new Session(new MockArraySessionStorage());
    }

    public function testWebhookReturnsJsonResponseFromWebhookRouteOnSuccess(): void
    {
        $webhookRoute = new FakeWebhookRoute();
        $webhookRoute->setResponse(new WebhookResponse(new Payment('payment-id-1')));

        $controller = $this->getController($webhookRoute);
        $response = $controller->webhook('subscription-id-42', new Request(), new FakeSalesChannelContext());

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame(1, $webhookRoute->getCallCount());
        $this->assertSame('subscription-id-42', $webhookRoute->getCalls()[0]['subscriptionId']);
    }

    public function testWebhookReturnsErrorJsonWithStatusCodeOnShopwareHttpException(): void
    {
        $webhookRoute = new FakeWebhookRoute();
        $webhookRoute->setException(new class extends HttpException {
            public function __construct()
            {
                parent::__construct(Response::HTTP_BAD_REQUEST, 'TEST_CODE', 'subscription not found');
            }
        });

        $controller = $this->getController($webhookRoute);
        $response = $controller->webhook('subscription-id-42', new Request(), new FakeSalesChannelContext());

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('subscription not found', $body['error']);
    }

    public function testWebhookReturnsUnprocessableEntityOnGenericThrowable(): void
    {
        $webhookRoute = new FakeWebhookRoute();
        $webhookRoute->setException(new \RuntimeException('something exploded'));

        $controller = $this->getController($webhookRoute);
        $response = $controller->webhook('subscription-id-42', new Request(), new FakeSalesChannelContext());

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
        $body = json_decode((string) $response->getContent(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('something exploded', $body['error']);
    }

    // ------------------------------------------------------- the logged-in guard

    /**
     * Every account action is reachable by URL. Without a logged-in customer they must not touch
     * the subscription of whoever the id belongs to.
     */
    public function testSubscriptionsListRedirectsToTheLoginWithoutACustomer(): void
    {
        $pageLoader = new FakeSubscriptionPageLoader();

        $response = $this->getController(pageLoader: $pageLoader)
            ->subscriptionsList(new Request(), new FakeSalesChannelContext())
        ;

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('frontend.account.login.page', $this->router->getLastRouteName());
        $this->assertSame(0, $pageLoader->getCallCount());
    }

    public function testChangeStateRedirectsToTheLoginWithoutACustomer(): void
    {
        $actionHandler = new FakeSubscriptionActionHandler();

        $this->getController(actionHandler: $actionHandler)
            ->changeState('subscription-1', $this->actionRequest('pause'), new FakeSalesChannelContext())
        ;

        $this->assertSame('frontend.account.login.page', $this->router->getLastRouteName());
        $this->assertSame(0, $actionHandler->getCallCount());
    }

    public function testUpdateBillingAddressRedirectsToTheLoginWithoutACustomer(): void
    {
        $route = new FakeUpdateAddressRoute();

        $this->getController(updateAddressRoute: $route)
            ->updateBillingAddress('subscription-1', new RequestDataBag(), new FakeSalesChannelContext())
        ;

        $this->assertSame('frontend.account.login.page', $this->router->getLastRouteName());
        $this->assertSame(0, $route->getCallCount());
    }

    public function testUpdateShippingAddressRedirectsToTheLoginWithoutACustomer(): void
    {
        $route = new FakeUpdateAddressRoute();

        $this->getController(updateAddressRoute: $route)
            ->updateShippingAddress('subscription-1', new RequestDataBag(), new FakeSalesChannelContext())
        ;

        $this->assertSame('frontend.account.login.page', $this->router->getLastRouteName());
        $this->assertSame(0, $route->getCallCount());
    }

    public function testUpdatePaymentStartRedirectsToTheLoginWithoutACustomer(): void
    {
        $route = new FakeUpdatePaymentMethodRoute();

        $this->getController(updatePaymentMethodRoute: $route)
            ->updatePaymentStart('subscription-1', new RequestDataBag(), new FakeSalesChannelContext())
        ;

        $this->assertSame('frontend.account.login.page', $this->router->getLastRouteName());
        $this->assertSame(0, $route->getCallCount());
    }

    public function testUpdatePaymentFinishRedirectsToTheLoginWithoutACustomer(): void
    {
        $route = new FakeUpdatePaymentMethodRoute();

        $this->getController(updatePaymentMethodRoute: $route)
            ->updatePaymentFinish('subscription-1', new FakeSalesChannelContext())
        ;

        $this->assertSame('frontend.account.login.page', $this->router->getLastRouteName());
        $this->assertSame(0, $route->getCallCount());
    }

    // ------------------------------------------------------------- changeState

    public function testChangeStateHandsTheActionAndTheSubscriptionToTheHandler(): void
    {
        $actionHandler = $this->succeedingActionHandler();

        $this->getController(actionHandler: $actionHandler)
            ->changeState('subscription-1', $this->actionRequest('pause'), $this->loggedInContext())
        ;

        $calls = $actionHandler->getCalls();
        $this->assertSame('pause', $calls[0]['action']);
        $this->assertSame('subscription-1', $calls[0]['subscriptionId']);
    }

    /**
     * The flash message is built from the action, so pausing must not report a cancellation.
     */
    public function testChangeStateFlashesTheSuccessMessageOfTheAction(): void
    {
        $this->getController(actionHandler: $this->succeedingActionHandler())
            ->changeState('subscription-1', $this->actionRequest('cancel'), $this->loggedInContext())
        ;

        $this->assertSame(
            ['molliePayments.subscriptions.account.successCancel'],
            $this->flashes('success')
        );
    }

    public function testChangeStateFlashesTheErrorMessageOfTheActionWhenItFails(): void
    {
        $actionHandler = new FakeSubscriptionActionHandler();
        $actionHandler->setException(new \RuntimeException('Mollie refused the pause'));

        $this->getController(actionHandler: $actionHandler)
            ->changeState('subscription-1', $this->actionRequest('pause'), $this->loggedInContext())
        ;

        $this->assertSame(
            ['molliePayments.subscriptions.account.errorPause'],
            $this->flashes('danger')
        );
    }

    public function testChangeStateReturnsToTheSubscriptionOverview(): void
    {
        $response = $this->getController(actionHandler: $this->succeedingActionHandler())
            ->changeState('subscription-1', $this->actionRequest('resume'), $this->loggedInContext())
        ;

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('frontend.account.mollie.subscriptions.page', $this->router->getLastRouteName());
    }

    // ------------------------------------------------------------- addresses

    public function testUpdateBillingAddressPassesTheSubscriptionToTheRoute(): void
    {
        $route = new FakeUpdateAddressRoute();

        $this->getController(updateAddressRoute: $route)
            ->updateBillingAddress('subscription-1', new RequestDataBag(), $this->loggedInContext())
        ;

        $calls = $route->getCalls();
        $this->assertSame('billing', $calls[0]['type']);
        $this->assertSame('subscription-1', $calls[0]['subscriptionId']);
    }

    public function testUpdateBillingAddressFlashesSuccess(): void
    {
        $this->getController()
            ->updateBillingAddress('subscription-1', new RequestDataBag(), $this->loggedInContext())
        ;

        $this->assertSame(
            ['molliePayments.subscriptions.account.successUpdateAddress'],
            $this->flashes('success')
        );
    }

    public function testUpdateBillingAddressFlashesAnErrorWhenTheRouteFails(): void
    {
        $route = new FakeUpdateAddressRoute();
        $route->setException(new \RuntimeException('Invalid address'));

        $this->getController(updateAddressRoute: $route)
            ->updateBillingAddress('subscription-1', new RequestDataBag(), $this->loggedInContext())
        ;

        $this->assertSame(
            ['molliePayments.subscriptions.account.errorUpdateAddress'],
            $this->flashes('danger')
        );
    }

    public function testUpdateShippingAddressPassesTheSubscriptionToTheRoute(): void
    {
        $route = new FakeUpdateAddressRoute();

        $this->getController(updateAddressRoute: $route)
            ->updateShippingAddress('subscription-1', new RequestDataBag(), $this->loggedInContext())
        ;

        $calls = $route->getCalls();
        $this->assertSame('shipping', $calls[0]['type']);
        $this->assertSame('subscription-1', $calls[0]['subscriptionId']);
    }

    public function testUpdateShippingAddressFlashesSuccess(): void
    {
        $this->getController()
            ->updateShippingAddress('subscription-1', new RequestDataBag(), $this->loggedInContext())
        ;

        $this->assertSame(
            ['molliePayments.subscriptions.account.successUpdateAddress'],
            $this->flashes('success')
        );
    }

    public function testUpdateShippingAddressFlashesAnErrorWhenTheRouteFails(): void
    {
        $route = new FakeUpdateAddressRoute();
        $route->setException(new \RuntimeException('Invalid address'));

        $this->getController(updateAddressRoute: $route)
            ->updateShippingAddress('subscription-1', new RequestDataBag(), $this->loggedInContext())
        ;

        $this->assertSame(
            ['molliePayments.subscriptions.account.errorUpdateAddress'],
            $this->flashes('danger')
        );
    }

    // ------------------------------------------------------ payment method update

    /**
     * The customer has to authorise the new mandate at Mollie, so the route's checkout URL is
     * where the browser goes - not back to the account page.
     */
    public function testUpdatePaymentStartSendsTheCustomerToTheMollieCheckout(): void
    {
        $route = new FakeUpdatePaymentMethodRoute('https://mollie.test/authorise-mandate');

        $response = $this->getController(updatePaymentMethodRoute: $route)
            ->updatePaymentStart('subscription-1', new RequestDataBag(), $this->loggedInContext())
        ;

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://mollie.test/authorise-mandate', $response->getTargetUrl());
    }

    public function testUpdatePaymentStartFlashesAnErrorAndStaysInTheAccountWhenItFails(): void
    {
        $route = new FakeUpdatePaymentMethodRoute();
        $route->setException(new \RuntimeException('Mandate could not be created'));

        $this->getController(updatePaymentMethodRoute: $route)
            ->updatePaymentStart('subscription-1', new RequestDataBag(), $this->loggedInContext())
        ;

        $this->assertSame(
            ['molliePayments.subscriptions.account.errorUpdatePayment'],
            $this->flashes('danger')
        );
        $this->assertSame('frontend.account.mollie.subscriptions.page', $this->router->getLastRouteName());
    }

    public function testUpdatePaymentFinishConfirmsTheSubscriptionAndFlashesSuccess(): void
    {
        $route = new FakeUpdatePaymentMethodRoute();

        $this->getController(updatePaymentMethodRoute: $route)
            ->updatePaymentFinish('subscription-1', $this->loggedInContext())
        ;

        $this->assertSame(1, $route->getCallCount());
        $this->assertSame(
            ['molliePayments.subscriptions.account.successUpdatePayment'],
            $this->flashes('success')
        );
    }

    public function testUpdatePaymentFinishFlashesAnErrorWhenTheConfirmationFails(): void
    {
        $route = new FakeUpdatePaymentMethodRoute();
        $route->setException(new \RuntimeException('Mandate not authorised'));

        $this->getController(updatePaymentMethodRoute: $route)
            ->updatePaymentFinish('subscription-1', $this->loggedInContext())
        ;

        $this->assertSame(
            ['molliePayments.subscriptions.account.errorUpdatePayment'],
            $this->flashes('danger')
        );
    }

    // ----------------------------------------------------------------- helpers

    /**
     * The action comes from the route defaults, not from the query - all five state routes point
     * at the same method.
     */
    private function succeedingActionHandler(): FakeSubscriptionActionHandler
    {
        $actionHandler = new FakeSubscriptionActionHandler();
        $actionHandler->setResponse(MollieSubscriptionBuilder::create()->withId('sub_1')->build());

        return $actionHandler;
    }

    private function actionRequest(string $action): Request
    {
        $request = new Request();
        $request->attributes->set('action', $action);

        return $request;
    }

    private function getController(
        ?FakeWebhookRoute $webhookRoute = null,
        ?FakeUpdateAddressRoute $updateAddressRoute = null,
        ?FakeUpdatePaymentMethodRoute $updatePaymentMethodRoute = null,
        ?FakeSubscriptionActionHandler $actionHandler = null,
        ?FakeSubscriptionPageLoader $pageLoader = null,
    ): SubscriptionController {
        $controller = new SubscriptionController(
            $webhookRoute ?? new FakeWebhookRoute(),
            $updateAddressRoute ?? new FakeUpdateAddressRoute(),
            $updatePaymentMethodRoute ?? new FakeUpdatePaymentMethodRoute(),
            $pageLoader ?? new FakeSubscriptionPageLoader(),
            $actionHandler ?? new FakeSubscriptionActionHandler(),
            new NullLogger()
        );

        $controller->setContainer($this->buildContainer());

        return $controller;
    }

    /**
     * StorefrontController reaches into the container for the router (redirects), the translator
     * (flash messages), the event dispatcher (StorefrontRedirectEvent) and the request stack
     * (the flash bag).
     */
    private function buildContainer(): Container
    {
        $request = Request::create('https://shop.test/account/mollie/subscriptions');
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $container = new Container();
        $container->set('router', $this->router);
        $container->set('event_dispatcher', new EventDispatcher());
        $container->set('request_stack', $requestStack);
        $container->set('translator', new IdentityTranslator());

        return $container;
    }

    private function loggedInContext(): FakeSalesChannelContext
    {
        $context = new FakeSalesChannelContext();
        $context->setCustomer(CustomerBuilder::create()->build());

        return $context;
    }

    /**
     * @return list<string>
     */
    private function flashes(string $type): array
    {
        return $this->session->getFlashBag()->peek($type);
    }
}
