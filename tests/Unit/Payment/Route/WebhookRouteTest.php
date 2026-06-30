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
use Mollie\Shopware\Unit\Fake\FakeOrderService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrderStateHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrderTransactionStateHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodUpdater;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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
        );
        $webhookRoute = $this->getRoute($transactionService, $fakeClient);

        $response = $webhookRoute->notify('test', $this->context);
        $this->assertInstanceOf(WebhookResponse::class, $response);
    }

    public function testWebhookForOutdatedTransactionIsSkipped(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->withNewerTransaction();

        $transactionStateHandler = new FakeOrderTransactionStateHandler();
        $orderStateHandler = new FakeOrderStateHandler();

        $webhookRoute = $this->getRoute($transactionService, null, $transactionStateHandler, null, $orderStateHandler);

        $response = $webhookRoute->notify('test', $this->context);

        $this->assertInstanceOf(WebhookResponse::class, $response);
        $this->assertFalse($transactionStateHandler->wasCalled(), 'Payment status must not change for an outdated transaction');
        $this->assertFalse($orderStateHandler->wasCalled(), 'Order status must not change for an outdated transaction');
    }

    private function getRoute(
        ?FakeTransactionService $transactionService = null,
        ?FakeClient $fakeClient = null,
        ?FakeOrderTransactionStateHandler $stateHandler = null,
        ?FakePaymentMethodUpdater $paymentMethodUpdater = null,
        ?FakeOrderStateHandler $orderStateHandler = null,
    ): WebhookRoute {
        if ($transactionService === null) {
            $transactionService = new FakeTransactionService();
            $transactionService->createValidStruct();
        }

        if ($fakeClient === null) {
            $fakeClient = new FakeClient('mollieTestId', 'paid');
        }

        $logger = new NullLogger();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $gateway = new MollieGateway($fakeClientFactory, $transactionService, $logger);

        return new WebhookRoute(
            $gateway,
            $stateHandler ?? new FakeOrderTransactionStateHandler(),
            new EventSpy(),
            $paymentMethodUpdater ?? new FakePaymentMethodUpdater(),
            $orderStateHandler ?? new FakeOrderStateHandler(),
            new FakeOrderService(),
            $logger
        );
    }
}
