<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Route;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\SessionWebhookRoute;
use Mollie\Shopware\Component\Payment\PaymentHandlerLocator;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeWebhookRoute;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionPaymentRepository;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakePayloadBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(SessionWebhookRoute::class)]
final class SessionWebhookRouteTest extends TestCase
{
    private const WEBHOOK_ID = '01a1a1e69fa671f3a5042d40067b1597';

    private FakeSessionPaymentRepository $sessionPaymentRepository;
    private FakeTransactionService $transactionService;
    private FakeGateway $gateway;
    private FakeWebhookRoute $webhookRoute;
    private FakeLogger $logger;

    protected function setUp(): void
    {
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository(FakeSessionPaymentRepository::entry(self::WEBHOOK_ID, 'ses_1', null, 'transaction-1'));
        $this->transactionService = new FakeTransactionService();
        $this->gateway = new FakeGateway(payment: $this->createPayment(PaymentMethod::CREDIT_CARD));
        $this->webhookRoute = new FakeWebhookRoute();
        $this->logger = new FakeLogger();
    }

    public function testUnknownWebhookIdIsRejected(): void
    {
        $exception = $this->notifyExpectingFailure('unknown-webhook-id');

        $this->assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        $this->assertSame(0, $this->gateway->getCallCount('getPayment'));
    }

    public function testPaymentIdIsStoredAndConflictReturnedWithoutTransaction(): void
    {
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository(FakeSessionPaymentRepository::entry(self::WEBHOOK_ID, 'ses_1', null, null));

        $exception = $this->notifyExpectingFailure(self::WEBHOOK_ID);

        $this->assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
        $this->assertSame('tr_1', $this->sessionPaymentRepository->findById(self::WEBHOOK_ID, Context::createDefaultContext())?->getPaymentId());
        $this->assertSame([], $this->webhookRoute->getNotifiedTransactionIds());
    }

    public function testPaymentIsAttachedToTheTransactionAsExpressCheckout(): void
    {
        $this->createRoute()->notify(self::WEBHOOK_ID, $this->createPostRequest(), Context::createDefaultContext());

        $saved = $this->transactionService->getSavedPaymentExtensions();
        $this->assertCount(1, $saved);
        $this->assertSame('transaction-1', $saved[0]['transactionId']);
        $this->assertSame('tr_1', $saved[0]['payment']->getId());
        $this->assertTrue($saved[0]['expressCheckout']);
    }

    public function testPaymentWebhookIsMovedToTheTransactionWithoutChangingTheRedirectUrl(): void
    {
        $this->createRoute()->notify(self::WEBHOOK_ID, $this->createPostRequest(), Context::createDefaultContext());

        $updates = $this->gateway->getUpdatePayloads();
        $this->assertCount(1, $updates);
        $this->assertSame('tr_1', $updates[0]['paymentId']);
        $this->assertSame('https://shop.test/mollie/webhook/transaction-1', $updates[0]['payment']->getWebhookUrl());
        $this->assertArrayNotHasKey('redirectUrl', $updates[0]['payment']->toArray());
    }

    public function testStatusIsLeftToTheShopwareFinalizeWhileTheShopperWaits(): void
    {
        $this->createRoute()->notify(self::WEBHOOK_ID, $this->createPostRequest(), Context::createDefaultContext());

        $this->assertSame([], $this->webhookRoute->getNotifiedTransactionIds());
    }

    public function testStatusIsSetByTheWebhookOnceTheShopperStoppedWaiting(): void
    {
        $this->sessionPaymentRepository->letShopperStopWaiting();

        $this->createRoute()->notify(self::WEBHOOK_ID, $this->createPostRequest(), Context::createDefaultContext());

        $this->assertSame(['transaction-1'], $this->webhookRoute->getNotifiedTransactionIds());
    }

    public function testPaymentIdIsReadFromTheQueryOfAGetRequest(): void
    {
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository(FakeSessionPaymentRepository::entry(self::WEBHOOK_ID, 'ses_1', null, null));

        $this->notifyExpectingFailure(self::WEBHOOK_ID, new Request(['id' => 'tr_from_query']));

        $this->assertSame('tr_from_query', $this->sessionPaymentRepository->findById(self::WEBHOOK_ID, Context::createDefaultContext())?->getPaymentId());
    }

    public function testSecondPaymentIdReplacesTheStoredOneWithAWarning(): void
    {
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository(FakeSessionPaymentRepository::entry(self::WEBHOOK_ID, 'ses_1', 'tr_old', null));

        $this->notifyExpectingFailure(self::WEBHOOK_ID);

        $this->assertSame('tr_1', $this->sessionPaymentRepository->findById(self::WEBHOOK_ID, Context::createDefaultContext())?->getPaymentId());
        $this->assertTrue($this->logger->hasRecordThatContains('warning', 'second payment id'));
    }

    public function testPaymentWithoutMethodIsRejected(): void
    {
        $this->gateway = new FakeGateway(payment: new Payment('tr_1'));

        $exception = $this->notifyExpectingFailure(self::WEBHOOK_ID);

        $this->assertSame(ExpressComponentsException::SESSION_PAYMENT_WITHOUT_METHOD, $exception->getErrorCode());
        $this->assertSame([], $this->transactionService->getSavedPaymentExtensions());
    }

    public function testPaymentMethodWithoutHandlerIsRejected(): void
    {
        $this->gateway = new FakeGateway(payment: $this->createPayment(PaymentMethod::IDEAL));

        $exception = $this->notifyExpectingFailure(self::WEBHOOK_ID);

        $this->assertSame(ExpressComponentsException::PAYMENT_HANDLER_NOT_FOUND, $exception->getErrorCode());
        $this->assertSame([], $this->transactionService->getSavedPaymentExtensions());
    }

    private function notifyExpectingFailure(string $webhookId, ?Request $request = null): ExpressComponentsException
    {
        try {
            $this->createRoute()->notify($webhookId, $request ?? $this->createPostRequest(), Context::createDefaultContext());
        } catch (ExpressComponentsException $exception) {
            return $exception;
        }

        $this->fail('The session webhook was expected to fail.');
    }

    private function createRoute(): SessionWebhookRoute
    {
        return new SessionWebhookRoute(
            $this->sessionPaymentRepository,
            $this->transactionService,
            $this->gateway,
            new FakePayloadBuilder(),
            new PaymentHandlerLocator([new FakePaymentMethodHandler(PaymentMethod::CREDIT_CARD)]),
            $this->webhookRoute,
            $this->logger
        );
    }

    private function createPostRequest(): Request
    {
        return new Request([], ['id' => 'tr_1']);
    }

    private function createPayment(PaymentMethod $method): Payment
    {
        $payment = new Payment('tr_1');
        $payment->setMethod($method);

        return $payment;
    }
}
