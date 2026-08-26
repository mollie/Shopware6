<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Controller;

use Mollie\Shopware\Component\FailureMode\PaymentPageFailedEvent;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Payment\Controller\PaymentController;
use Mollie\Shopware\Component\Payment\Route\WebhookException;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeHttpKernel;
use Mollie\Shopware\Unit\Fake\FakeRequestTransformer;
use Mollie\Shopware\Unit\Fake\FakeRouter;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\FakeWebhookRoute;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentTokenRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * The return route is the customer coming back from Mollie. It normally forwards to Shopware's own
 * finalize controller; only when the finalize token is gone does it redirect by payment status
 * itself. The finalize URL carries that token as a query parameter - it is never sent to Mollie.
 */
#[CoversClass(PaymentController::class)]
final class PaymentControllerTest extends TestCase
{
    private const TRANSACTION_ID = 'abc123';
    private const FINALIZE_URL = 'https://shop.test/payment/finalize?_sw_payment_token=tok_1&other=keep';

    private Context $context;

    private FakeSalesChannelContext $salesChannelContext;

    private FakeGateway $gateway;

    private FakeWebhookRoute $webhookRoute;

    private EventSpy $eventDispatcher;

    private FakePaymentTokenRepository $paymentTokenRepository;

    private FakeRouter $router;

    private FakeHttpKernel $httpKernel;

    private RequestStack $requestStack;

    private PaymentSettings $paymentSettings;

    /** @var array<string, mixed> */
    private array $inheritableAttributes = [];

    protected function setUp(): void
    {
        $this->context = new Context(new SystemSource());
        $this->salesChannelContext = new FakeSalesChannelContext('sales-channel-1', 'cart-token', $this->context);
        $this->webhookRoute = new FakeWebhookRoute();
        $this->eventDispatcher = new EventSpy();
        $this->paymentTokenRepository = new FakePaymentTokenRepository();
        $this->router = new FakeRouter('https://shop.test/target');
        $this->httpKernel = new FakeHttpKernel();
        $this->requestStack = new RequestStack();
        // forward() duplicates the current request, so the stack is never empty in production.
        $this->requestStack->push(Request::create('https://shop.test/mollie/payment/' . self::TRANSACTION_ID));
        $this->paymentSettings = new PaymentSettings('', 0);
        $this->gateway = new FakeGateway('', $this->payment(PaymentStatus::PAID));
    }

    // ----------------------------------------------------------------- webhook

    public function testWebhookNotifiesTheRouteWithTheTransactionId(): void
    {
        $this->controller()->webhook(self::TRANSACTION_ID, $this->context);

        $this->assertSame([self::TRANSACTION_ID], $this->webhookRoute->getNotifiedTransactionIds());
    }

    public function testWebhookAnswersWithThePaymentTheRouteReturned(): void
    {
        $response = $this->controller()->webhook(self::TRANSACTION_ID, $this->context);

        // WebhookResponse wraps the payment in an ArrayStruct, which lifts its keys to the top.
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(self::TRANSACTION_ID, $body['payment']['id']);
        $this->assertSame('success', $body['status']);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * A Shopware exception carries its own status code, so the webhook must answer with that one
     * instead of a blanket error - Mollie retries based on the status.
     */
    public function testWebhookAnswersWithTheStatusCodeOfAShopwareException(): void
    {
        $this->webhookRoute->addFailingTransactionId(
            self::TRANSACTION_ID,
            WebhookException::transactionWithoutOrder(self::TRANSACTION_ID)
        );

        $response = $this->controller()->webhook(self::TRANSACTION_ID, $this->context);

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('Shopware order not found for TransactionId: abc123', $body['error']);
    }

    public function testWebhookAnswersWithUnprocessableEntityForAnUnexpectedError(): void
    {
        $this->webhookRoute->addFailingTransactionId(self::TRANSACTION_ID);

        $response = $this->controller()->webhook(self::TRANSACTION_ID, $this->context);

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertFalse($body['success']);
        $this->assertSame('Mollie API unavailable', $body['error']);
    }

    // ------------------------------------------------------------------ return

    /**
     * Mollie sends the transaction id back in the case it received it in; the lookup is done with
     * the lowercase id Shopware stores.
     */
    public function testReturnLooksThePaymentUpWithTheLowercasedTransactionId(): void
    {
        $this->controller()->return('ABC123', $this->salesChannelContext);

        $this->assertSame(self::TRANSACTION_ID, $this->gateway->getLastTransactionId());
    }

    public function testReturnForwardsToTheShopwareFinalizeController(): void
    {
        $response = $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame('forwarded', $response->getContent());
    }

    /**
     * The token never travels to Mollie - Mollie's redirect URL has a length limit that truncates
     * the JWT. It is read back out of the finalize URL stored on the transaction instead.
     */
    public function testReturnForwardsTheQueryParametersOfTheFinalizeUrl(): void
    {
        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $forwarded = $this->httpKernel->getHandledRequest();
        $this->assertSame('tok_1', $forwarded->query->get('_sw_payment_token'));
        $this->assertSame('keep', $forwarded->query->get('other'));
    }

    /**
     * Without "sw-skip-transformer" the HttpKernel runs the request transformer again on the
     * sub-request, stripping the domain path prefix a second time and losing the sales channel.
     */
    public function testReturnForwardsWithTheTransformerSkippedAndTheInheritableAttributes(): void
    {
        $this->inheritableAttributes = ['sw-sales-channel-id' => 'sales-channel-1'];

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $forwarded = $this->httpKernel->getHandledRequest();
        $this->assertTrue($forwarded->attributes->get('sw-skip-transformer'));
        $this->assertSame('sales-channel-1', $forwarded->attributes->get('sw-sales-channel-id'));
    }

    public function testReturnDispatchesTheFailedEventForAFailedPayment(): void
    {
        $this->gateway = new FakeGateway('', $this->payment(PaymentStatus::FAILED));

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $event = $this->firstEventOfType(PaymentPageFailedEvent::class);
        $this->assertSame(self::TRANSACTION_ID, $event->getTransactionId());
    }

    /**
     * With Shopware's own failure handling switched on, the plugin must stay out of the way -
     * otherwise the customer is sent through two competing error flows.
     */
    public function testReturnDispatchesNoFailedEventWhenShopwareHandlesFailedPayments(): void
    {
        $this->gateway = new FakeGateway('', $this->payment(PaymentStatus::FAILED));
        $this->paymentSettings = new PaymentSettings('', 0, shopwareFailedPayment: true);

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame(0, $this->countEventsOfType(PaymentPageFailedEvent::class));
    }

    public function testReturnDispatchesNoFailedEventForAPaidPayment(): void
    {
        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame(0, $this->countEventsOfType(PaymentPageFailedEvent::class));
    }

    public function testReturnDispatchesNoFailedEventWithoutAnOrderOnTheTransaction(): void
    {
        $this->gateway = new FakeGateway('', $this->payment(PaymentStatus::FAILED, withOrder: false));

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame(0, $this->countEventsOfType(PaymentPageFailedEvent::class));
    }

    /**
     * Reloading the return URL would finalize a second time. Shopware's finalize controller only
     * answers with an error then, so the customer is sent to the page their payment status deserves.
     */
    public function testReturnSkipsFinalizeForAnAlreadyConsumedToken(): void
    {
        $this->paymentTokenRepository->withConsumedToken('tok_1');

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame('frontend.checkout.finish.page', $this->router->getLastRouteName());
        $this->assertSame(['orderId' => 'order-1'], $this->router->getLastParameters());
    }

    public function testReturnSendsAnUnapprovedPaymentWithAConsumedTokenToTheEditOrderPage(): void
    {
        $this->gateway = new FakeGateway('', $this->payment(PaymentStatus::FAILED));
        $this->paymentTokenRepository->withConsumedToken('tok_1');
        $this->paymentSettings = new PaymentSettings('', 0, shopwareFailedPayment: true);

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame('frontend.account.edit-order.page', $this->router->getLastRouteName());
    }

    public function testReturnSendsAConsumedTokenWithoutAnOrderToTheOrderOverview(): void
    {
        $this->gateway = new FakeGateway('', $this->payment(PaymentStatus::PAID, withOrder: false));
        $this->paymentTokenRepository->withConsumedToken('tok_1');

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame('frontend.account.order.page', $this->router->getLastRouteName());
    }

    public function testReturnAnswersWithARedirectForAConsumedToken(): void
    {
        $this->paymentTokenRepository->withConsumedToken('tok_1');

        $response = $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testReturnFinalizesWhenTheTokenIsNotConsumedYet(): void
    {
        $response = $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame(['tok_1'], $this->paymentTokenRepository->getCheckedTokens());
        $this->assertSame('forwarded', $response->getContent());
    }

    public function testReturnForwardsWithoutCheckingATokenWhenTheFinalizeUrlCarriesNone(): void
    {
        $this->gateway = new FakeGateway('', $this->payment(PaymentStatus::PAID, finalizeUrl: 'https://shop.test/payment/finalize'));

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame([], $this->paymentTokenRepository->getCheckedTokens());
    }

    /**
     * A token Shopware itself rejects means the same thing as an already consumed one: finalize
     * cannot run, so the customer is redirected by payment status instead of seeing an error page.
     */
    public function testReturnRedirectsByPaymentStatusWhenFinalizeRejectsTheToken(): void
    {
        $this->httpKernel->withFailure(PaymentException::tokenInvalidated('tok_1'));

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame('frontend.checkout.finish.page', $this->router->getLastRouteName());
    }

    public function testReturnRedirectsByPaymentStatusWhenFinalizeReportsAnInvalidToken(): void
    {
        $this->httpKernel->withFailure(PaymentException::invalidToken('tok_1'));

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);

        $this->assertSame('frontend.checkout.finish.page', $this->router->getLastRouteName());
    }

    /**
     * Any other payment failure is a real error and must not be swallowed into a redirect.
     */
    public function testReturnRethrowsAPaymentExceptionThatIsNotAboutTheToken(): void
    {
        $this->httpKernel->withFailure(PaymentException::unknownPaymentMethodById('payment-method-1'));

        $this->expectException(PaymentException::class);

        $this->controller()->return(self::TRANSACTION_ID, $this->salesChannelContext);
    }

    // ----------------------------------------------------------------- helpers

    private function payment(PaymentStatus $status, bool $withOrder = true, string $finalizeUrl = self::FINALIZE_URL): Payment
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');

        if ($withOrder) {
            $order = new OrderEntity();
            $order->setId('order-1');
            $order->setOrderNumber('10000');
            $transaction->setOrder($order);
        }

        $payment = new Payment('tr_1');
        $payment->setStatus($status);
        $payment->setFinalizeUrl($finalizeUrl);
        $payment->setShopwareTransaction($transaction);

        return $payment;
    }

    private function controller(): PaymentController
    {
        $controller = new PaymentController(
            $this->gateway,
            $this->webhookRoute,
            new FakeSettingsService(paymentSettings: $this->paymentSettings),
            $this->eventDispatcher,
            $this->paymentTokenRepository,
            new NullLogger()
        );

        $controller->setContainer($this->buildContainer());

        return $controller;
    }

    /**
     * StorefrontController reaches into the container for the router (redirects), the event
     * dispatcher (StorefrontRedirectEvent) and the kernel plus request stack (forwarding).
     */
    private function buildContainer(): Container
    {
        $container = new Container();
        $container->set('router', $this->router);
        $container->set('event_dispatcher', new EventDispatcher());
        $container->set('request_stack', $this->requestStack);
        $container->set('http_kernel', $this->httpKernel);
        $container->set(RequestTransformerInterface::class, new FakeRequestTransformer($this->inheritableAttributes));

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

    /**
     * @param class-string $eventClass
     */
    private function countEventsOfType(string $eventClass): int
    {
        $count = 0;
        foreach ($this->eventDispatcher->getEvents() as $event) {
            if ($event instanceof $eventClass) {
                ++$count;
            }
        }

        return $count;
    }
}
