<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Action;

use Mollie\Shopware\Component\Mollie\LineItemFilter;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixer;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Payment\PayloadBuilder;
use Mollie\Shopware\Component\Payment\Transaction\MollieTransactionStruct;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrdersApiAwarePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrderTransactionStateHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakePaymentMethodHandler;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(Pay::class)]
final class PayTest extends TestCase
{
    public function testPayActionRedirectToMollieCheckoutUrl(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();
        $expectedUrl = 'https://mollie.com/checkout=token=123';

        $payAction = $this->getPayAction($transactionService, $expectedUrl);

        $response = $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($expectedUrl, $response->getTargetUrl());
    }

    public function testPayActionRedirectToShopwareReturnUrl(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();
        $expectedUrl = 'returnUrl';

        $payAction = $this->getPayAction($transactionService, $expectedUrl);

        $response = $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($expectedUrl, $response->getTargetUrl());
    }

    public function testOrdersApiHandlerCallsCreateOrderNotCreatePayment(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();
        $expectedUrl = 'https://mollie.com/orders/checkout=order=123';

        $gateway = new FakeGateway($expectedUrl);
        $payAction = $this->getPayAction($transactionService, $expectedUrl, $gateway);

        $response = $payAction->execute(new FakeOrdersApiAwarePaymentHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($expectedUrl, $response->getTargetUrl());
        $this->assertCount(1, $gateway->getCreateOrderPayloads());
        $this->assertCount(0, $gateway->getCreatePayloads());
    }

    public function testPaymentsApiHandlerCallsCreatePaymentNotCreateOrder(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();
        $expectedUrl = 'https://mollie.com/checkout=token=456';

        $gateway = new FakeGateway($expectedUrl);
        $payAction = $this->getPayAction($transactionService, $expectedUrl, $gateway);

        $response = $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame($expectedUrl, $response->getTargetUrl());
        $this->assertCount(0, $gateway->getCreateOrderPayloads());
        $this->assertCount(1, $gateway->getCreatePayloads());
    }

    public function testPaymentsApiCancelsOldCancelablePaymentAndCreatesNewOne(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields(['paymentId' => 'tr_old_payment']);
        $transactionService->createTransaction();
        $expectedUrl = 'https://mollie.com/checkout=token=789';

        $oldPayment = new Payment('tr_old_payment');
        $oldPayment->setCheckoutUrl($expectedUrl);
        $oldPayment->setCancelable(true);

        $gateway = new FakeGateway($expectedUrl, $oldPayment);
        $payAction = $this->getPayAction($transactionService, $expectedUrl, $gateway);

        $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertSame(['tr_old_payment'], $gateway->getCancelledPaymentIds());
        $this->assertCount(1, $gateway->getCreatePayloads());
        $this->assertCount(0, $gateway->getUpdatePayloads());
    }

    public function testPaymentsApiUpdatesOldPaymentWhenItCannotBeCancelled(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields(['paymentId' => 'tr_old_payment']);
        $transactionService->createTransaction();
        $expectedUrl = 'https://mollie.com/checkout=token=012';

        $oldPayment = new Payment('tr_old_payment');
        $oldPayment->setCheckoutUrl($expectedUrl);
        $oldPayment->setCancelable(false);

        $gateway = new FakeGateway($expectedUrl, $oldPayment);
        $payAction = $this->getPayAction($transactionService, $expectedUrl, $gateway);

        $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertCount(0, $gateway->getCancelledPaymentIds());
        $this->assertCount(0, $gateway->getCreatePayloads());
        $updatePayloads = $gateway->getUpdatePayloads();
        $this->assertCount(1, $updatePayloads);
        $this->assertSame('tr_old_payment', $updatePayloads[0]['molliePaymentId']);

        $updateArray = $updatePayloads[0]['payload']->toArray();
        $this->assertArrayNotHasKey('amount', $updateArray);
        $this->assertArrayNotHasKey('lines', $updateArray);
        $this->assertArrayNotHasKey('sequenceType', $updateArray);
        $this->assertArrayHasKey('method', $updateArray);
        $this->assertArrayHasKey('redirectUrl', $updateArray);
    }

    public function testPaymentsApiIncrementsPaymentCounterStoredOnOrder(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields(['paymentId' => 'tr_old_payment', 'countPayments' => 1]);
        $transactionService->createTransaction();
        $expectedUrl = 'https://mollie.com/checkout=token=345';

        $oldPayment = new Payment('tr_old_payment');
        $oldPayment->setCheckoutUrl($expectedUrl);
        $oldPayment->setCancelable(true);

        $gateway = new FakeGateway($expectedUrl, $oldPayment);
        $payAction = $this->getPayAction($transactionService, $expectedUrl, $gateway);

        $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $createPayloads = $gateway->getCreatePayloads();
        $this->assertCount(1, $createPayloads);
        $this->assertStringEndsWith('-2', $createPayloads[0]->getDescription());
    }

    public function testPaymentsApiDoesNotSuffixDescriptionOnFirstAttempt(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();
        $expectedUrl = 'https://mollie.com/checkout=token=678';

        $gateway = new FakeGateway($expectedUrl);
        $payAction = $this->getPayAction($transactionService, $expectedUrl, $gateway);

        $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $createPayloads = $gateway->getCreatePayloads();
        $this->assertCount(1, $createPayloads);
        $this->assertStringEndsNotWith('-2', $createPayloads[0]->getDescription());
    }

    public function testOrdersApiHandlerPassesAuthenticationIdAtRootLevel(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();

        $gateway = new FakeGateway('https://mollie.com/orders/checkout');
        $payAction = $this->getPayAction($transactionService, 'https://mollie.com/orders/checkout', $gateway);

        $dataBag = new RequestDataBag();
        $dataBag->set('authenticationId', 'auth_express_token');

        $payAction->execute(new FakeOrdersApiAwarePaymentHandler(), new MollieTransactionStruct('test', 'returnUrl'), $dataBag, new Context(new SystemSource()));

        $orderPayloads = $gateway->getCreateOrderPayloads();
        $this->assertCount(1, $orderPayloads);

        $orderArray = $orderPayloads[0]->toArray();
        $this->assertArrayHasKey('authenticationId', $orderArray);
        $this->assertSame('auth_express_token', $orderArray['authenticationId']);
        $this->assertArrayNotHasKey('payment', $orderArray);
    }

    private function getPayAction(FakeTransactionService $transactionService, string $checkoutUrl, ?FakeGateway $gateway = null): Pay
    {
        $eventDispatcher = new EventSpy();
        $fakeRouteBuilder = new FakeRouteBuilder();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings);

        $fakeOrderTransactionStateHandler = new FakeOrderTransactionStateHandler();
        $fakeCustomerRepository = new FakeCustomerRepository();
        $logger = new NullLogger();
        $gateway = $gateway ?? new FakeGateway($checkoutUrl);
        $lineItemAnalyzer = new LineItemAnalyzer();
        $lineItemFilter = new LineItemFilter();
        $roundingDifferenceFixer = new RoundingDifferenceFixer();
        $builder = new PayloadBuilder($fakeRouteBuilder, $settingsService,$gateway,$lineItemAnalyzer,$fakeCustomerRepository,$lineItemFilter,$roundingDifferenceFixer,$logger);

        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->setSession(new \Symfony\Component\HttpFoundation\Session\Session(
            new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
        ));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new Pay($transactionService, $builder, $gateway, $fakeOrderTransactionStateHandler, $fakeRouteBuilder, $eventDispatcher, $requestStack, $logger);
    }
}
