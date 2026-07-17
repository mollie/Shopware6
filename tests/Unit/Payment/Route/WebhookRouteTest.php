<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Payment\Route\WebhookException;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeOrderService;
use Mollie\Shopware\Unit\Fake\FakeShipOrderRoute;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrderStateHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrderTransactionStateHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodUpdater;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(WebhookRoute::class)]
final class WebhookRouteTest extends TestCase
{
    private Context $context;

    public function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    public function testWebhookIsSuccessful(): void
    {
        $webhookRoute = $this->getRoute();
        $response = $webhookRoute->notify('test', $this->context);
        $this->assertInstanceOf(WebhookResponse::class, $response);
        $this->assertInstanceOf(Payment::class, $response->getPayment());
    }

    public function testTransactionWithoutOrderExceptionIsThrown(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->withoutOrder();

        $webhookRoute = $this->getRoute($transactionService);
        try {
            $webhookRoute->notify('test', $this->context);
            $this->fail('Expected WebhookException was not thrown');
        } catch (WebhookException $exception) {
            $this->assertSame(WebhookException::TRANSACTION_WITHOUT_ORDER, $exception->getErrorCode());
        }
    }

    public function testWebhookWithOpenStatusSkipsPaymentStatusChange(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $fakeClient = new FakeClient('mollieTestId', 'open');
        $webhookRoute = $this->getRoute($transactionService, $fakeClient);

        $response = $webhookRoute->notify('test', $this->context);
        $this->assertInstanceOf(WebhookResponse::class, $response);
    }

    /**
     * open and pending have no shopware transition by design. The minutely status-update task
     * re-checks not-yet-paid orders, so these known statuses must be skipped silently instead of
     * logging a warning on every poll.
     */
    #[DataProvider('knownStatusesWithoutTransitionProvider')]
    public function testWebhookWithKnownNoTransitionStatusDoesNotLogWarning(string $status): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $logger = new FakeLogger();
        $fakeClient = new FakeClient('mollieTestId', $status);
        $webhookRoute = $this->getRoute($transactionService, $fakeClient, null, null, null, null, null, null, $logger);

        $webhookRoute->notify('test', $this->context);

        $this->assertFalse(
            $logger->hasRecordThatContains(LogLevel::WARNING, 'Failed to find shopware handler method for status'),
            sprintf('Status "%s" must not log a missing-handler warning', $status)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function knownStatusesWithoutTransitionProvider(): array
    {
        return [
            'open' => ['open'],
            'pending' => ['pending'],
        ];
    }

    public function testPaymentStatusChangeFailedThrowsWebhookException(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $stateHandler = new FakeOrderTransactionStateHandler();
        $stateHandler->setShouldThrow(true);

        $fakeClient = new FakeClient('mollieTestId', 'paid');
        $webhookRoute = $this->getRoute($transactionService, $fakeClient, $stateHandler);

        try {
            $webhookRoute->notify('test', $this->context);
            $this->fail('Expected WebhookException was not thrown');
        } catch (WebhookException $exception) {
            $this->assertSame(WebhookException::PAYMENT_STATUS_CHANGE_FAILED, $exception->getErrorCode());
        }
    }

    public function testPaymentWithoutMethodThrowsWebhookException(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $fakeClient = new FakeClient('mollieTestId', 'paid', null);
        $webhookRoute = $this->getRoute($transactionService, $fakeClient);

        try {
            $webhookRoute->notify('test', $this->context);
            $this->fail('Expected WebhookException was not thrown');
        } catch (WebhookException $exception) {
            $this->assertSame(WebhookException::PAYMENT_WITHOUT_METHOD, $exception->getErrorCode());
        }
    }

    public function testTransactionWithoutPaymentMethodThrowsWebhookException(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->withoutPaymentMethod();

        $webhookRoute = $this->getRoute($transactionService);

        try {
            $webhookRoute->notify('test', $this->context);
            $this->fail('Expected WebhookException was not thrown');
        } catch (WebhookException $exception) {
            $this->assertSame(WebhookException::TRANSACTION_WITHOUT_PAYMENT_METHOD, $exception->getErrorCode());
        }
    }

    public function testTransactionWithoutMolliePaymentThrowsWebhookException(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->withoutMollieExtensionOnPaymentMethod();

        $webhookRoute = $this->getRoute($transactionService);

        try {
            $webhookRoute->notify('test', $this->context);
            $this->fail('Expected WebhookException was not thrown');
        } catch (WebhookException $exception) {
            $this->assertSame(WebhookException::TRANSACTION_WITHOUT_MOLLIE_PAYMENT, $exception->getErrorCode());
        }
    }

    public function testPaymentMethodChangeFailedThrowsWebhookException(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $paymentMethodUpdater = new FakePaymentMethodUpdater();
        $paymentMethodUpdater->setShouldThrow(true);

        $webhookRoute = $this->getRoute($transactionService, null, null, $paymentMethodUpdater);

        try {
            $webhookRoute->notify('test', $this->context);
            $this->fail('Expected WebhookException was not thrown');
        } catch (WebhookException $exception) {
            $this->assertSame(WebhookException::PAYMENT_METHOD_CHANGE_FAILED, $exception->getErrorCode());
        }
    }

    public function testOrderStatusChangeFailedThrowsWebhookException(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $orderStateHandler = new FakeOrderStateHandler();
        $orderStateHandler->setShouldThrow(true);

        $webhookRoute = $this->getRoute($transactionService, null, null, null, $orderStateHandler);

        try {
            $webhookRoute->notify('test', $this->context);
            $this->fail('Expected WebhookException was not thrown');
        } catch (WebhookException $exception) {
            $this->assertSame(WebhookException::ORDER_STATUS_CHANGE_FAILED, $exception->getErrorCode());
        }
    }

    /**
     * When the transaction is already in the target payment state, Shopware throws an
     * IllegalTransitionException. The webhook must skip the change and finish successfully
     * instead of failing with a WebhookException.
     */
    public function testPaymentStatusAlreadyReachedIsSkipped(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $stateHandler = new FakeOrderTransactionStateHandler();
        $stateHandler->setShouldThrowIllegalTransition(true);

        $fakeClient = new FakeClient('mollieTestId', 'paid');
        $webhookRoute = $this->getRoute($transactionService, $fakeClient, $stateHandler);

        $response = $webhookRoute->notify('test', $this->context);

        $this->assertInstanceOf(WebhookResponse::class, $response);
    }

    /**
     * When the transaction is already in the target payment state, the webhook must not call the
     * state machine at all, so the IllegalTransitionException can never be raised in the first place.
     */
    public function testPaymentStatusTransitionSkippedWhenAlreadyInTargetState(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->withTransactionState(OrderTransactionStates::STATE_PAID);

        $stateHandler = new FakeOrderTransactionStateHandler();

        $fakeClient = new FakeClient('mollieTestId', 'paid');
        $webhookRoute = $this->getRoute($transactionService, $fakeClient, $stateHandler);

        $response = $webhookRoute->notify('test', $this->context);

        $this->assertInstanceOf(WebhookResponse::class, $response);
        $this->assertFalse($stateHandler->wasCalled(), 'Payment status must not change when the transaction is already in the target state');
    }

    /**
     * When the order is already in the target state, performTransition throws an
     * IllegalTransitionException. The webhook must skip the change and finish successfully.
     */
    public function testOrderStatusAlreadyReachedIsSkipped(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $orderStateHandler = new FakeOrderStateHandler();
        $orderStateHandler->setShouldThrowIllegalTransition(true);

        $webhookRoute = $this->getRoute($transactionService, null, null, null, $orderStateHandler);

        $response = $webhookRoute->notify('test', $this->context);

        $this->assertInstanceOf(WebhookResponse::class, $response);
    }

    /**
     * When the delivery is already shipped, the delivery state transition throws an
     * IllegalTransitionException. The webhook must skip the change and finish successfully.
     */
    public function testDeliveryStatusAlreadyReachedIsSkipped(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $orderService = new FakeOrderService();
        $orderService->setShouldThrowIllegalTransition(true);

        $fakeClient = new FakeClient(
            'mollieTestId',
            'paid',
            PaymentMethod::PAYPAL,
            false,
            null,
            ['value' => '100.00', 'currency' => 'EUR'],
            ['value' => '100.00', 'currency' => 'EUR'],
        );

        $webhookRoute = $this->getRoute($transactionService, $fakeClient, null, null, null, $orderService);

        $response = $webhookRoute->notify('test', $this->context);

        $this->assertInstanceOf(WebhookResponse::class, $response);
    }

    public function testWebhookWithCapturedAmountShipsOrder(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $fakeClient = new FakeClient(
            'mollieTestId',
            'paid',
            PaymentMethod::PAYPAL,
            false,
            null,
            ['value' => '100.00', 'currency' => 'EUR'],
            ['value' => '100.00', 'currency' => 'EUR'],
        );
        $webhookRoute = $this->getRoute($transactionService, $fakeClient);

        $response = $webhookRoute->notify('test', $this->context);
        $this->assertInstanceOf(WebhookResponse::class, $response);
    }

    /**
     * The webhook reflects only its own transaction's payment. There is no order-wide guard anymore,
     * so even a lower status (here: failed) updates that transaction and dispatches the events;
     * duplicate payments are resolved separately by the reconciler.
     */
    public function testLowerStatusStillUpdatesItsOwnTransaction(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->withOrderTransactionStates(OrderTransactionStates::STATE_PAID);

        $transactionStateHandler = new FakeOrderTransactionStateHandler();
        $eventSpy = new EventSpy();

        $fakeClient = new FakeClient('mollieTestId', 'failed');
        $webhookRoute = $this->getRoute($transactionService, $fakeClient, $transactionStateHandler, null, null, null, $eventSpy);

        $response = $webhookRoute->notify('test', $this->context);

        $this->assertInstanceOf(WebhookResponse::class, $response);
        $this->assertTrue($transactionStateHandler->wasCalled(), 'The webhook must update its own transaction regardless of other transactions');
        $this->assertGreaterThanOrEqual(2, $eventSpy->getEventCount(), 'Webhook events must still be dispatched');
    }

    /**
     * A second payment that also completes as "paid" (e.g. the order was re-paid with another method)
     * updates the payment method, payment status and order status.
     */
    public function testSecondPaidPaymentOverwritesAlreadyPaidOrder(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->withOrderTransactionStates(OrderTransactionStates::STATE_PAID);

        $transactionStateHandler = new FakeOrderTransactionStateHandler();
        $orderStateHandler = new FakeOrderStateHandler();
        $paymentMethodUpdater = new FakePaymentMethodUpdater();

        $fakeClient = new FakeClient('mollieTestId', 'paid');
        $webhookRoute = $this->getRoute($transactionService, $fakeClient, $transactionStateHandler, $paymentMethodUpdater, $orderStateHandler);

        $response = $webhookRoute->notify('test', $this->context);

        $this->assertInstanceOf(WebhookResponse::class, $response);
        $this->assertTrue($transactionStateHandler->wasCalled(), 'A second paid payment must update the payment status');
        $this->assertTrue($orderStateHandler->wasCalled(), 'A second paid payment must update the order status');
        $this->assertTrue($paymentMethodUpdater->wasCalled(), 'A second paid payment must update the payment method');
    }

    /**
     * Refunds and chargebacks legitimately change the state of an already paid order, so the payment
     * and order status updates must still run for them.
     */
    public function testRefundIsAppliedEvenWhenOrderAlreadyPaid(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->withOrderTransactionStates(OrderTransactionStates::STATE_PAID);

        $transactionStateHandler = new FakeOrderTransactionStateHandler();
        $orderStateHandler = new FakeOrderStateHandler();

        $fakeClient = new FakeClient(
            'mollieTestId',
            'paid',
            PaymentMethod::PAYPAL,
            false,
            null,
            null,
            ['value' => '100.00', 'currency' => 'EUR'],
            ['value' => '50.00', 'currency' => 'EUR'],
        );
        $webhookRoute = $this->getRoute($transactionService, $fakeClient, $transactionStateHandler, null, $orderStateHandler);

        $response = $webhookRoute->notify('test', $this->context);

        $this->assertInstanceOf(WebhookResponse::class, $response);
        $this->assertTrue($transactionStateHandler->wasCalled(), 'Refund must still change the payment status of a paid order');
        $this->assertTrue($orderStateHandler->wasCalled(), 'Refund must still change the order status of a paid order');
    }

    private function getRoute(
        ?FakeTransactionService $transactionService = null,
        ?FakeClient $fakeClient = null,
        ?FakeOrderTransactionStateHandler $stateHandler = null,
        ?FakePaymentMethodUpdater $paymentMethodUpdater = null,
        ?FakeOrderStateHandler $orderStateHandler = null,
        ?FakeOrderService $orderService = null,
        ?EventSpy $eventSpy = null,
        ?FakeShipOrderRoute $shipOrderRoute = null,
        ?LoggerInterface $logger = null,
    ): WebhookRoute {
        if ($transactionService === null) {
            $transactionService = new FakeTransactionService();
            $transactionService->createValidStruct();
        }

        if ($fakeClient === null) {
            $fakeClient = new FakeClient('mollieTestId', 'paid');
        }

        $logger = $logger ?? new NullLogger();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $gateway = new MollieGateway($fakeClientFactory, $transactionService, $logger);
        $shipOrderRoute = $shipOrderRoute ?? new FakeShipOrderRoute();

        return new WebhookRoute(
            $gateway,
            $stateHandler ?? new FakeOrderTransactionStateHandler(),
            $eventSpy ?? new EventSpy(),
            $paymentMethodUpdater ?? new FakePaymentMethodUpdater(),
            $orderStateHandler ?? new FakeOrderStateHandler(),
            $orderService ?? new FakeOrderService(),
            $shipOrderRoute,
            $logger
        );
    }
}
