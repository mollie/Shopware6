<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Route;

use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Route\WebhookException;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Mollie\Shopware\Component\Payment\Route\WebhookRoute;
use Mollie\Shopware\Component\Transaction\TransactionServiceInterface;
use Mollie\Shopware\Unit\Fake\FakeEventDispatcher;
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
            $response = $webhookRoute->notify('test', $this->context);
        } catch (WebhookException $exception) {
            $this->assertSame(WebhookException::TRANSACTION_WITHOUT_ORDER,$exception->getErrorCode());
        }
    }

    private function getRoute(?TransactionServiceInterface $transactionService = null): WebhookRoute
    {
        if ($transactionService === null) {
            $transactionService = new FakeTransactionService();
            $transactionService->createValidStruct();
        }

        $logger = new NullLogger();
        $fakeClient = new FakeClient('mollieTestId', 'paid');
        $fakeClientFactory = new FakeClientFactory($fakeClient);

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, $logger);

        return new WebhookRoute(
            $gateway,
            new FakeOrderTransactionStateHandler(),
            new FakeEventDispatcher(),
            new FakePaymentMethodUpdater(),
            new FakeOrderStateHandler(),
            new FakeOrderService(),
            $logger
        );
    }
}
