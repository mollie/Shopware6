<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Action;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Mollie\RoundingDifferenceFixer;
use Mollie\Shopware\Component\Payment\Action\Pay;
use Mollie\Shopware\Component\Payment\Method\PosPayment;
use Mollie\Shopware\Component\Payment\PayloadBuilder;
use Mollie\Shopware\Component\Payment\Transaction\MollieTransactionStruct;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Component\Subscription\LineItemAnalyzer;
use Mollie\Shopware\Unit\Builder\LineItemFilterBuilder;
use Mollie\Shopware\Unit\Fake\EventSpy;
use Mollie\Shopware\Unit\Fake\FakeCustomerRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeBankTransferPaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeFinalize;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrdersApiAwarePaymentHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakeOrderTransactionStateHandler;
use Mollie\Shopware\Unit\Payment\Fake\FakePay;
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

    public function testOpenExistingPaymentIsReusedViaUpdate(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->createTransaction();

        $existing = new Payment('testMollieId');
        $existing->setStatus(PaymentStatus::OPEN);
        $existing->setCheckoutUrl('https://mollie.com/checkout=token=open');
        $gateway = new FakeGateway('https://mollie.com/checkout=token=open', $existing);

        $payAction = $this->getPayAction($transactionService, 'https://mollie.com/checkout=token=open', $gateway);

        $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertCount(1, $gateway->getUpdatePayloads());
        $this->assertCount(0, $gateway->getCreatePayloads());
        $this->assertSame('testMollieId', $gateway->getUpdatePayloads()[0]['paymentId']);
    }

    public function testCancelableExistingPaymentIsCancelledAndRecreated(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->createTransaction();

        $existing = new Payment('testMollieId');
        $existing->setStatus(PaymentStatus::OPEN);
        $existing->setCancelable(true);
        $existing->setCheckoutUrl('https://mollie.com/checkout=token=new');
        $gateway = new FakeGateway('https://mollie.com/checkout=token=new', $existing);

        $payAction = $this->getPayAction($transactionService, 'https://mollie.com/checkout=token=new', $gateway);

        $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertSame(['testMollieId'], $gateway->getCancelledPaymentIds());
        $this->assertCount(1, $gateway->getCreatePayloads());
        $this->assertCount(0, $gateway->getUpdatePayloads());
    }

    public function testPendingOrderSessionKeyIsSetForNonBankTransferPayment(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();

        $requestStack = $this->createRequestStack();
        $payAction = $this->getPayAction($transactionService, 'https://mollie.com/checkout', null, $requestStack);

        $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertNotEmpty($requestStack->getSession()->get(Pay::SESSION_KEY_PENDING_ORDER));
    }

    public function testPendingOrderSessionKeyIsNotSetForBankTransferPayment(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();

        $requestStack = $this->createRequestStack();
        $payAction = $this->getPayAction($transactionService, 'https://mollie.com/checkout', null, $requestStack);

        $payAction->execute(new FakeBankTransferPaymentHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertNull($requestStack->getSession()->get(Pay::SESSION_KEY_PENDING_ORDER));
    }

    /**
     * Mollie does not answer with a checkout url for every method (a POS or an already finalized
     * payment has none). The customer then goes straight to the Shopware finish page instead of a
     * redirect to nowhere.
     */
    public function testAPaymentWithoutACheckoutUrlSendsTheCustomerToTheShopwareFinishPage(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();

        $payAction = $this->getPayAction($transactionService, '');

        $response = $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'https://shop.test/finish'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://shop.test/finish', $response->getTargetUrl());
    }

    public function testAnOrdersApiPaymentWithoutACheckoutUrlSendsTheCustomerToTheShopwareFinishPage(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();

        $payAction = $this->getPayAction($transactionService, '');

        $response = $payAction->execute(new FakeOrdersApiAwarePaymentHandler(), new MollieTransactionStruct('test', 'https://shop.test/finish'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://shop.test/finish', $response->getTargetUrl());
    }

    /**
     * A terminal payment is not paid in a browser, so the customer stays in the shop on the
     * plugin's own terminal page instead of being sent to Mollie.
     */
    public function testATerminalPaymentGoesToTheTerminalPageInsteadOfMollie(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();

        $routeBuilder = new FakeRouteBuilder(posCheckoutUrl: 'https://shop.test/mollie/pos/checkout');
        $payAction = $this->getPayAction($transactionService, 'https://mollie.com/checkout', routeBuilder: $routeBuilder);

        $response = $payAction->execute($this->posPaymentHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://shop.test/mollie/pos/checkout', $response->getTargetUrl());
    }

    /**
     * The payment already exists at Mollie at this point. A state machine that refuses the
     * transition must not strand the customer before the redirect.
     */
    public function testARefusedStatusTransitionStillRedirectsTheCustomerToMollie(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();

        $stateHandler = new FakeOrderTransactionStateHandler();
        $stateHandler->setShouldThrowIllegalTransition(true);

        $payAction = $this->getPayAction($transactionService, 'https://mollie.com/checkout', stateHandler: $stateHandler);

        $response = $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://mollie.com/checkout', $response->getTargetUrl());
    }

    /**
     * The credit card and iDEAL components let the customer pick the method in the shop, so the
     * choice has to reach Mollie instead of the full list of the profile.
     */
    public function testTheMethodTheCustomerPickedInTheShopIsSentToMollie(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createTransaction();

        $gateway = new FakeGateway('https://mollie.com/checkout');
        $payAction = $this->getPayAction($transactionService, 'https://mollie.com/checkout', $gateway);

        $dataBag = new RequestDataBag();
        $dataBag->set('paymentMethods', ['ideal', 'creditcard']);

        $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), $dataBag, new Context(new SystemSource()));

        $this->assertSame(['ideal', 'creditcard'], $gateway->getCreatePayloads()[0]->toArray()['method']);
    }

    /**
     * An express checkout is paid inside the wallet before the order exists. Creating a second
     * payment would charge the shopper twice, so the paid one is updated instead - and without a
     * redirect url, which Mollie refuses on a finalized payment.
     */
    public function testAnAlreadyPaidExistingPaymentIsUpdatedInsteadOfCreatedAgain(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();
        $transactionService->createTransaction();

        $existing = new Payment('testMollieId');
        $existing->setStatus(PaymentStatus::PAID);
        $existing->setCheckoutUrl('https://mollie.com/checkout=token=paid');
        $gateway = new FakeGateway('https://mollie.com/checkout=token=paid', $existing);

        $payAction = $this->getPayAction($transactionService, 'https://mollie.com/checkout=token=paid', $gateway);

        $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertCount(1, $gateway->getUpdatePayloads());
        $this->assertCount(0, $gateway->getCreatePayloads());
        $this->assertArrayNotHasKey('redirectUrl', $gateway->getUpdatePayloads()[0]['payment']->toArray());
    }

    /**
     * Several Mollie payments of the same order have to be distinguishable in the Mollie dashboard,
     * so every attempt after the first carries its number in the description.
     */
    public function testARetryCarriesTheAttemptNumberInTheDescription(): void
    {
        $transactionService = new FakeTransactionService();
        $transactionService->withOrderTransactionStates('cancelled', 'open');
        $transactionService->createTransaction();

        $gateway = new FakeGateway('https://mollie.com/checkout');
        $payAction = $this->getPayAction($transactionService, 'https://mollie.com/checkout', $gateway);

        $payAction->execute(new FakePaymentMethodHandler(), new MollieTransactionStruct('test', 'returnUrl'), new RequestDataBag(), new Context(new SystemSource()));

        $this->assertStringEndsWith('-1', $gateway->getCreatePayloads()[0]->getDescription());
    }

    private function posPaymentHandler(): PosPayment
    {
        return new PosPayment(new FakePay(), new FakeFinalize(), new NullLogger());
    }

    private function createRequestStack(): RequestStack
    {
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->setSession(new \Symfony\Component\HttpFoundation\Session\Session(
            new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
        ));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    private function getPayAction(
        FakeTransactionService $transactionService,
        string $checkoutUrl,
        ?FakeGateway $gateway = null,
        ?RequestStack $requestStack = null,
        ?FakeRouteBuilder $routeBuilder = null,
        ?FakeOrderTransactionStateHandler $stateHandler = null,
    ): Pay {
        $eventDispatcher = new EventSpy();
        $fakeRouteBuilder = $routeBuilder ?? new FakeRouteBuilder();
        $paymentSettings = new PaymentSettings('test_{ordernumber}-{customernumber}', 0);
        $settingsService = new FakeSettingsService(paymentSettings: $paymentSettings);

        $fakeOrderTransactionStateHandler = $stateHandler ?? new FakeOrderTransactionStateHandler();
        $fakeCustomerRepository = new FakeCustomerRepository();
        $logger = new NullLogger();
        $gateway = $gateway ?? new FakeGateway($checkoutUrl);
        $lineItemAnalyzer = new LineItemAnalyzer();
        $lineItemFilter = LineItemFilterBuilder::build();
        $roundingDifferenceFixer = new RoundingDifferenceFixer();
        $builder = new PayloadBuilder($fakeRouteBuilder, $settingsService,$gateway,$lineItemAnalyzer,$fakeCustomerRepository,$lineItemFilter,$roundingDifferenceFixer,$logger);

        if ($requestStack === null) {
            $request = new \Symfony\Component\HttpFoundation\Request();
            $request->setSession(new \Symfony\Component\HttpFoundation\Session\Session(
                new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
            ));
            $requestStack = new RequestStack();
            $requestStack->push($request);
        }

        return new Pay($transactionService, $builder, $gateway, $fakeOrderTransactionStateHandler, $fakeRouteBuilder, $eventDispatcher, $requestStack, $logger);
    }
}
