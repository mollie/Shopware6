<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\PaymentLink\Controller;

use Mollie\Shopware\Component\Mollie\Address;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentCollection;
use Mollie\Shopware\Component\Mollie\PaymentMethod as MolliePaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Mollie\SequenceType;
use Mollie\Shopware\Component\Payment\Event\ModifyCreatePaymentLinkPayloadEvent;
use Mollie\Shopware\Component\Payment\Event\PaymentLinkCreatedEvent;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Component\PaymentLink\Controller\PaymentLinkController;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Builder\PaymentMethodBuilder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeEntityRepository;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeRouter;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContextPersister;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\FakeShopwareAccountService;
use Mollie\Shopware\Unit\Fake\FakeTokenFactory;
use Mollie\Shopware\Unit\Payment\Fake\FakePayloadBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Payment\MethodRemover\Fake\FakePaymentMethodRoute;
use Mollie\Shopware\Unit\PaymentLink\Fake\FakePaymentLinkGateway;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The pay route turns an order into a Mollie payment link the customer can open from a mail. It
 * logs the order's customer in so the checkout finish page works on return, and it reuses an
 * existing link instead of creating a second one.
 */
#[CoversClass(PaymentLinkController::class)]
final class PaymentLinkControllerTest extends TestCase
{
    private const ORDER_ID = 'order-1';
    private const TRANSACTION_ID = 'transaction-1';
    private const CUSTOMER_ID = 'test-customer-id';
    private const GENERATED_URL = 'https://shop.test/generated';

    private Context $context;

    private FakeSalesChannelContext $salesChannelContext;

    private FakeOrderSearchRepository $orderRepository;

    private FakeEntityRepository $orderTransactionRepository;

    private FakeTransactionService $transactionService;

    private FakePayloadBuilder $payloadBuilder;

    private FakePaymentLinkGateway $paymentLinkGateway;

    private FakePaymentMethodRoute $paymentMethodRoute;

    private FakeShopwareAccountService $accountService;

    private FakeSalesChannelContextPersister $contextPersister;

    private FakeTokenFactory $tokenFactory;

    private FakeRouter $router;

    private EventSpy $eventDispatcher;

    private RequestStack $requestStack;

    private Session $session;

    private PaymentSettings $paymentSettings;

    private SystemConfigService $systemConfigService;

    /** @var list<FakePaymentMethodHandler> */
    private array $paymentHandlers = [];

    protected function setUp(): void
    {
        $this->context = new Context(new SystemSource());
        $this->salesChannelContext = new FakeSalesChannelContext('sales-channel-1', 'cart-token', $this->context);
        $this->orderRepository = new FakeOrderSearchRepository();
        $this->orderTransactionRepository = new FakeEntityRepository(new OrderTransactionDefinition());
        $this->transactionService = new FakeTransactionService();
        $this->payloadBuilder = new FakePayloadBuilder();
        $this->paymentLinkGateway = new FakePaymentLinkGateway();
        $this->paymentMethodRoute = new FakePaymentMethodRoute(new PaymentMethodCollection());
        $this->accountService = new FakeShopwareAccountService();
        $this->contextPersister = new FakeSalesChannelContextPersister();
        $this->tokenFactory = new FakeTokenFactory();
        $this->router = new FakeRouter(self::GENERATED_URL);
        $this->eventDispatcher = new EventSpy();
        $this->paymentSettings = new PaymentSettings('', 0);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->paymentHandlers = [new FakePaymentMethodHandler(MolliePaymentMethod::PAYPAL)];

        $this->session = new Session(new MockArraySessionStorage());
        $request = Request::create('https://shop.test/mollie/pay/' . self::ORDER_ID);
        $request->setSession($this->session);
        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);

        $this->orderTransactionRepository->entityWrittenContainerEvents[] = new EntityWrittenContainerEvent(
            $this->context,
            new NestedEventCollection(),
            []
        );
    }

    // -------------------------------------------------------------- early exits

    public function testPayRedirectsToTheOrderOverviewForAnUnknownOrder(): void
    {
        $response = $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('frontend.account.order.page', $this->router->getLastRouteName());
    }

    /**
     * A payment link only makes sense while the order still awaits payment.
     */
    public function testPayRedirectsToTheOrderOverviewWithoutAPayableTransaction(): void
    {
        $this->givenOrder(transactionState: OrderTransactionStates::STATE_PAID);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame('frontend.account.order.page', $this->router->getLastRouteName());
        $this->assertSame([], $this->paymentLinkGateway->getCreatedPayloads());
    }

    public function testPayRedirectsToTheOrderOverviewForANonMollieOrder(): void
    {
        $this->givenOrder(mollieMethod: null);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame('frontend.account.order.page', $this->router->getLastRouteName());
        $this->assertSame([], $this->paymentLinkGateway->getCreatedPayloads());
    }

    /**
     * The existing link was already paid, so neither a new nor an updated link makes sense.
     */
    public function testPayCreatesNoLinkWhenTheExistingOneWasAlreadyPaid(): void
    {
        $this->givenOrder(existingPaymentLinkId: 'pl_existing');
        $this->paymentLinkGateway->withPayments($this->paymentsWithStatus(PaymentStatus::PAID));

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame('frontend.account.order.page', $this->router->getLastRouteName());
        $this->assertSame([], $this->paymentLinkGateway->getCreatedPayloads());
        $this->assertSame([], $this->paymentLinkGateway->getUpdatedPayloads());
    }

    public function testPayUpdatesTheExistingLinkWhenItsPaymentIsStillOpen(): void
    {
        $this->givenOrder(existingPaymentLinkId: 'pl_existing');
        $this->paymentLinkGateway->withPayments($this->paymentsWithStatus(PaymentStatus::OPEN));

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $updated = $this->paymentLinkGateway->getUpdatedPayloads();
        $this->assertCount(1, $updated);
        $this->assertSame('pl_existing', $updated[0]['paymentLinkId']);
        $this->assertSame([], $this->paymentLinkGateway->getCreatedPayloads());
    }

    public function testPayCreatesANewLinkWithoutAnExistingOne(): void
    {
        $this->givenOrder();

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertCount(1, $this->paymentLinkGateway->getCreatedPayloads());
        $this->assertSame([], $this->paymentLinkGateway->getUpdatedPayloads());
    }

    // ---------------------------------------------------------- allowed methods

    public function testPayAllowsOnlyTheMethodTheOrderWasPlacedWith(): void
    {
        $this->givenOrder(mollieMethod: MolliePaymentMethod::PAYPAL);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame(['paypal'], $this->payloadBuilder->getLastPaymentLinkCall()['allowedMethods']);
    }

    /**
     * Sending an unsupported method makes Mollie reject the whole request, so no restriction is
     * sent instead - Mollie then offers every method of the profile.
     */
    public function testPaySendsNoRestrictionWhenTheOrdersMethodHasNoPaymentLinkSupport(): void
    {
        $this->givenOrder(mollieMethod: MolliePaymentMethod::DIRECT_DEBIT);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame([], $this->payloadBuilder->getLastPaymentLinkCall()['allowedMethods']);
    }

    public function testPayOffersEveryAvailableMollieMethodWhenTheSettingAllowsIt(): void
    {
        $this->givenOrder();
        $this->allowMethodSelection();
        $this->givenAvailableMethods(MolliePaymentMethod::CREDIT_CARD, MolliePaymentMethod::BLIK);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame(['creditcard', 'blik'], $this->payloadBuilder->getLastPaymentLinkCall()['allowedMethods']);
    }

    public function testPayDropsAnAvailableMethodThatPaymentLinksDoNotSupport(): void
    {
        $this->givenOrder();
        $this->allowMethodSelection();
        $this->givenAvailableMethods(MolliePaymentMethod::CREDIT_CARD, MolliePaymentMethod::DIRECT_DEBIT);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame(['creditcard'], $this->payloadBuilder->getLastPaymentLinkCall()['allowedMethods']);
    }

    /**
     * Several Shopware methods map to the same Mollie method - the Orders and Payments API variants
     * of PayPal, for instance. Mollie must not receive it twice.
     */
    public function testPayDropsDuplicateMollieMethods(): void
    {
        $this->givenOrder();
        $this->allowMethodSelection();
        $this->givenAvailableMethods(MolliePaymentMethod::PAYPAL, MolliePaymentMethod::PAYPAL);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame(['paypal'], $this->payloadBuilder->getLastPaymentLinkCall()['allowedMethods']);
    }

    public function testPayIgnoresANonMollieMethodAmongTheAvailableOnes(): void
    {
        $this->givenOrder();
        $this->allowMethodSelection();
        $methods = new PaymentMethodCollection([
            PaymentMethodBuilder::create()->withId('method-1')->withMollieMethod(MolliePaymentMethod::BLIK)->build(),
            PaymentMethodBuilder::create()->withId('method-2')->withName('Invoice')->build(),
        ]);
        $this->paymentMethodRoute = new FakePaymentMethodRoute($methods);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame(['blik'], $this->payloadBuilder->getLastPaymentLinkCall()['allowedMethods']);
    }

    /**
     * The removers can strip every link-supported method. Falling back to the order's own method
     * keeps the link payable instead of offering nothing.
     */
    public function testPayFallsBackToTheOrdersMethodWhenNoAvailableMethodIsLinkSupported(): void
    {
        $this->givenOrder(mollieMethod: MolliePaymentMethod::PAYPAL);
        $this->allowMethodSelection();
        $this->givenAvailableMethods(MolliePaymentMethod::DIRECT_DEBIT);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame(['paypal'], $this->payloadBuilder->getLastPaymentLinkCall()['allowedMethods']);
    }

    // ------------------------------------------------------------------ handler

    /**
     * With a single allowed method the link targets that method, so its handler is passed in to
     * apply the method's payment-specific parameters.
     */
    public function testPayResolvesTheHandlerOfASingleAllowedMethod(): void
    {
        $this->givenOrder(mollieMethod: MolliePaymentMethod::PAYPAL);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $handler = $this->payloadBuilder->getLastPaymentLinkCall()['handler'];
        $this->assertInstanceOf(FakePaymentMethodHandler::class, $handler);
        $this->assertSame(MolliePaymentMethod::PAYPAL, $handler->getPaymentMethod());
    }

    public function testPayResolvesNoHandlerForSeveralAllowedMethods(): void
    {
        $this->givenOrder();
        $this->allowMethodSelection();
        $this->givenAvailableMethods(MolliePaymentMethod::CREDIT_CARD, MolliePaymentMethod::BLIK);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertNull($this->payloadBuilder->getLastPaymentLinkCall()['handler']);
    }

    public function testPayResolvesNoHandlerWhenNoMethodIsRestricted(): void
    {
        $this->givenOrder(mollieMethod: MolliePaymentMethod::DIRECT_DEBIT);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertNull($this->payloadBuilder->getLastPaymentLinkCall()['handler']);
    }

    // ------------------------------------------------------------ payload + link

    /**
     * The payload event is an extension point: what a listener puts on the event is what has to
     * reach Mollie.
     */
    public function testPaySendsThePayloadTheModifyEventCarries(): void
    {
        $this->givenOrder();
        $replacement = $this->paymentLinkPayload();
        $this->eventDispatcher->addListener(
            ModifyCreatePaymentLinkPayloadEvent::class,
            function (ModifyCreatePaymentLinkPayloadEvent $event) use ($replacement): void {
                $event->setPaymentLink($replacement);
            }
        );

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $created = $this->paymentLinkGateway->getCreatedPayloads();
        $this->assertSame($replacement, $created[0]);
    }

    public function testPayRedirectsTheCustomerToTheMolliePaymentLink(): void
    {
        $this->givenOrder();

        $response = $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://mollie.test/pl_created', $response->getTargetUrl());
    }

    public function testPayStoresThePaymentLinkIdAndTheFinalizeUrlOnTheTransaction(): void
    {
        $this->givenOrder();

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $row = $this->orderTransactionRepository->data[0][0];
        $this->assertSame(self::TRANSACTION_ID, $row['id']);
        $this->assertSame('pl_created', $row['customFields'][Mollie::EXTENSION]['paymentLinkId']);
        $this->assertSame(self::GENERATED_URL, $row['customFields'][Mollie::EXTENSION]['finalizeUrl']);
    }

    public function testPayKeepsTheExistingMollieCustomFieldsOfTheTransaction(): void
    {
        $this->givenOrder(transactionCustomFields: [Mollie::EXTENSION => ['keepMe' => 'yes']]);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $row = $this->orderTransactionRepository->data[0][0];
        $this->assertSame('yes', $row['customFields'][Mollie::EXTENSION]['keepMe']);
    }

    // ------------------------------------------------------------------- login

    /**
     * The customer opens the link from a mail and is not logged in, so the checkout finish page
     * would redirect to the cart. The order's customer is logged into the session instead.
     */
    public function testPayLogsTheOrderCustomerIn(): void
    {
        $this->givenOrder();

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame(self::CUSTOMER_ID, $this->accountService->getLoggedInId());
    }

    /**
     * AccountService only builds the logged-in context in memory. Without persisting the customer
     * under the new token, the return request rebuilds an anonymous context.
     */
    public function testPayPersistsTheCustomerUnderTheNewContextToken(): void
    {
        $this->givenOrder();

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $saved = $this->contextPersister->getSaved();
        $this->assertCount(1, $saved);
        $this->assertSame('fake-token', $saved[0]['token']);
        $this->assertSame(self::CUSTOMER_ID, $saved[0]['customerId']);
        $this->assertSame('sales-channel-1', $saved[0]['salesChannelId']);
    }

    public function testPayMarksTheLoginAsTemporary(): void
    {
        $this->givenOrder();

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertTrue($this->session->get(PaymentLinkController::TEMPORARY_LOGIN_SESSION_KEY));
    }

    /**
     * A failed login must not block the payment - the finish page falls back to its cart redirect,
     * but the link itself still works.
     */
    public function testPayStillCreatesTheLinkWhenTheLoginFails(): void
    {
        $this->givenOrder();
        $this->accountService->withFailure(new \RuntimeException('Customer is inactive'));

        $response = $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertCount(1, $this->paymentLinkGateway->getCreatedPayloads());
        $this->assertSame('https://mollie.test/pl_created', $response->getTargetUrl());
        $this->assertSame([], $this->contextPersister->getSaved());
    }

    // ------------------------------------------------------------------- events

    public function testPayDispatchesThePaymentLinkCreatedEvent(): void
    {
        $this->givenOrder();

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $event = $this->firstEventOfType(PaymentLinkCreatedEvent::class);
        $this->assertSame('https://mollie.test/pl_created', $event->getPaymentLinkUrl());
    }

    // ------------------------------------------------------------- finalize url

    /**
     * The token is issued here and stored on the transaction; it is never sent to Mollie, whose
     * redirect URL has a length limit that truncates the JWT.
     */
    public function testPayIssuesAFinalizeTokenForTheOrdersTransaction(): void
    {
        $this->givenOrder();

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $tokenStruct = $this->tokenFactory->getLastTokenStruct();
        $this->assertSame(self::TRANSACTION_ID, $tokenStruct->getTransactionId());
        $this->assertSame(self::GENERATED_URL, $tokenStruct->getFinishUrl());
        $this->assertSame(self::GENERATED_URL, $tokenStruct->getErrorUrl());
    }

    /**
     * Shopware configures the finalize window in minutes; the token wants seconds.
     */
    public function testPayConvertsTheConfiguredFinalizeWindowToSeconds(): void
    {
        $this->givenOrder();
        $this->systemConfigService = new StaticSystemConfigService([
            'sales-channel-1' => ['core.cart.paymentFinalizeTransactionTime' => 45],
        ]);

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        $this->assertSame(2700, $this->tokenFactory->getLastTokenStruct()->getExpires());
    }

    public function testPayLeavesTheTokenLifetimeToShopwareWhenItIsNotConfigured(): void
    {
        $this->givenOrder();

        $this->controller()->pay(self::ORDER_ID, $this->salesChannelContext);

        // TokenStruct falls back to 1800 seconds when it is handed no lifetime.
        $this->assertSame(1800, $this->tokenFactory->getLastTokenStruct()->getExpires());
    }

    // ----------------------------------------------------------------- helpers

    /**
     * @param array<string, mixed> $transactionCustomFields
     */
    private function givenOrder(
        string $transactionState = OrderTransactionStates::STATE_OPEN,
        ?MolliePaymentMethod $mollieMethod = MolliePaymentMethod::PAYPAL,
        ?string $existingPaymentLinkId = null,
        array $transactionCustomFields = [],
    ): void {
        $state = new StateMachineStateEntity();
        $state->setId('transaction-state');
        $state->setTechnicalName($transactionState);

        $paymentMethodBuilder = PaymentMethodBuilder::create()->withId('payment-method-1');
        if ($mollieMethod !== null) {
            $paymentMethodBuilder = $paymentMethodBuilder->withMollieMethod($mollieMethod);
        }

        $transaction = new OrderTransactionEntity();
        $transaction->setId(self::TRANSACTION_ID);
        $transaction->setPaymentMethodId('payment-method-1');
        $transaction->setPaymentMethod($paymentMethodBuilder->build());
        $transaction->setStateMachineState($state);
        $transaction->setCustomFields($transactionCustomFields);
        $transaction->setCreatedAt(new \DateTimeImmutable('2026-01-01T10:00:00+00:00'));

        if ($existingPaymentLinkId !== null) {
            $payment = new Payment('tr_1');
            $payment->setPaymentLinkId($existingPaymentLinkId);
            $transaction->addExtension(Mollie::EXTENSION, $payment);
        }

        $order = new OrderEntity();
        $order->setId(self::ORDER_ID);
        $order->setOrderNumber('10000');
        $order->setSalesChannelId('sales-channel-1');
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $this->orderRepository->add($order);
    }

    private function allowMethodSelection(): void
    {
        $this->paymentSettings = new PaymentSettings('', 0, paymentLinkAllowMethodSelection: true);
    }

    private function givenAvailableMethods(MolliePaymentMethod ...$mollieMethods): void
    {
        $collection = new PaymentMethodCollection();
        foreach ($mollieMethods as $index => $mollieMethod) {
            $collection->add(
                PaymentMethodBuilder::create()
                    ->withId('available-method-' . $index)
                    ->withMollieMethod($mollieMethod)
                    ->build()
            );
        }

        $this->paymentMethodRoute = new FakePaymentMethodRoute($collection);
    }

    private function paymentLinkPayload(): CreatePaymentLink
    {
        $address = new Address('customer@shop.test', 'Mr', 'Max', 'Mustermann', 'Teststreet 1', '12345', 'Testcity', 'DE');

        return new CreatePaymentLink(
            'Replaced by a listener',
            'https://shop.test/return',
            new Money(25.0, 'EUR'),
            new LineItemCollection(),
            $address,
            $address,
            SequenceType::ONEOFF
        );
    }

    private function paymentsWithStatus(PaymentStatus $status): PaymentCollection
    {
        $payment = new Payment('tr_link');
        $payment->setStatus($status);

        return new PaymentCollection([$payment]);
    }

    private function controller(): PaymentLinkController
    {
        $controller = new PaymentLinkController(
            $this->orderRepository,
            $this->orderTransactionRepository,
            $this->transactionService,
            $this->payloadBuilder,
            $this->paymentLinkGateway,
            $this->paymentMethodRoute,
            new FakeSettingsService(paymentSettings: $this->paymentSettings),
            new PaymentHandlerLocator($this->paymentHandlers),
            $this->tokenFactory,
            $this->accountService,
            $this->contextPersister,
            $this->requestStack,
            $this->eventDispatcher,
            $this->systemConfigService,
            new NullLogger()
        );

        $controller->setContainer($this->buildContainer());

        return $controller;
    }

    /**
     * StorefrontController reaches into the container for the router (redirects and generateUrl),
     * the translator (flash messages), the event dispatcher (StorefrontRedirectEvent) and the
     * request stack (the flash bag).
     */
    private function buildContainer(): Container
    {
        $container = new Container();
        $container->set('router', $this->router);
        $container->set('event_dispatcher', new EventDispatcher());
        $container->set('request_stack', $this->requestStack);
        // Answers every snippet with its own key, which is all the flash messages need here.
        $container->set('translator', new IdentityTranslator());

        return $container;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $eventClass
     *
     * @return T
     */
    private function firstEventOfType(string $eventClass): object
    {
        foreach ($this->eventDispatcher->getEvents() as $event) {
            if ($event instanceof $eventClass) {
                return $event;
            }
        }

        throw new \RuntimeException(sprintf('No %s was dispatched.', $eventClass));
    }
}
