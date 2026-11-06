<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\FinishUrls;
use Mollie\Shopware\Component\Payment\ExpressComponents\PaymentFinalizer;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Fake\FakeTokenFactory;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionPaymentRepository;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionWebhookRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(PaymentFinalizer::class)]
final class PaymentFinalizerTest extends TestCase
{
    private const FINALIZE_URL = 'https://shop.test/payment/finalize-transaction?_sw_payment_token=generated-token';

    private FakeSessionPaymentRepository $sessionPaymentRepository;
    private FakeSessionWebhookRoute $sessionWebhookRoute;
    private FakeTokenFactory $tokenFactory;
    private RequestStack $requestStack;
    private FakeLogger $logger;

    protected function setUp(): void
    {
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository(FakeSessionPaymentRepository::entry('webhook-id', 'ses_1', 'tr_1'));
        $this->sessionWebhookRoute = new FakeSessionWebhookRoute();
        $this->tokenFactory = new FakeTokenFactory();
        $this->requestStack = new RequestStack();
        $this->logger = new FakeLogger();
    }

    public function testNewestTransactionIsAssignedToTheSession(): void
    {
        $order = $this->createOrder(
            $this->createTransaction('transaction-old', '2026-11-03 10:00:00'),
            $this->createTransaction('transaction-new', '2026-11-03 10:05:00'),
        );

        $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame('transaction-new', $this->sessionPaymentRepository->findById('webhook-id', Context::createDefaultContext())?->getTransactionId());
    }

    public function testShopperIsSentToTheShopwareFinalizeWhenThePaymentIdIsKnown(): void
    {
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $redirectUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame(self::FINALIZE_URL, $redirectUrl);
        $this->assertFalse($this->sessionPaymentRepository->hasWaitedInVain());
    }

    public function testPaymentIsAttachedToTheTransactionBeforeTheFinalize(): void
    {
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame([['webhookId' => 'webhook-id', 'paymentId' => 'tr_1']], $this->sessionWebhookRoute->getCalls());
    }

    public function testFinalizeTokenIsIssuedForTheTransactionAndItsPaymentMethod(): void
    {
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $tokenStruct = $this->tokenFactory->getLastTokenStruct();
        $this->assertSame('transaction-1', $tokenStruct->getTransactionId());
        $this->assertSame('payment-method-1', $tokenStruct->getPaymentMethodId());
    }

    public function testFinalizeTokenCarriesTheFinishAndErrorUrlOfTheRequest(): void
    {
        $this->requestStack->push(new Request([
            FinishUrls::FINISH_URL_PARAMETER => 'https://pwa.test/finish/' . FinishUrls::ORDER_ID_PLACEHOLDER,
            FinishUrls::ERROR_URL_PARAMETER => 'https://pwa.test/error/' . FinishUrls::ORDER_ID_PLACEHOLDER,
        ]));
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $tokenStruct = $this->tokenFactory->getLastTokenStruct();
        $this->assertSame('https://pwa.test/finish/order-1', $tokenStruct->getFinishUrl());
        $this->assertSame('https://pwa.test/error/order-1', $tokenStruct->getErrorUrl());
    }

    public function testFinalizeTokenFallsBackToTheStorefrontPagesWithoutUrlsInTheRequest(): void
    {
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $tokenStruct = $this->tokenFactory->getLastTokenStruct();
        $this->assertSame('https://shop.test/checkout/finish', $tokenStruct->getFinishUrl());
        $this->assertSame('https://shop.test/account/order/edit', $tokenStruct->getErrorUrl());
    }

    public function testShopperWaitsForAWebhookThatArrivesMeanwhile(): void
    {
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository(FakeSessionPaymentRepository::entry('webhook-id', 'ses_1'));
        $this->sessionPaymentRepository->letPaymentIdArriveWhileWaiting('tr_1');
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $redirectUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame(self::FINALIZE_URL, $redirectUrl);
        $this->assertSame([['webhookId' => 'webhook-id', 'paymentId' => 'tr_1']], $this->sessionWebhookRoute->getCalls());
    }

    public function testShopperGetsTheFinishPageWhenTheWebhookDoesNotArriveInTime(): void
    {
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository(FakeSessionPaymentRepository::entry('webhook-id', 'ses_1'));
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $redirectUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://shop.test/checkout/finish', $redirectUrl);
        $this->assertTrue($this->sessionPaymentRepository->hasWaitedInVain());
        $this->assertSame([], $this->sessionWebhookRoute->getCalls());
    }

    public function testShopperGetsTheFinishPageWhenThePaymentCannotBeAttached(): void
    {
        $this->sessionWebhookRoute = new FakeSessionWebhookRoute(new \RuntimeException('Mollie API not reachable'));
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $redirectUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://shop.test/checkout/finish', $redirectUrl);
        $this->assertTrue($this->logger->hasRecordThatContains('error', 'could not be attached during the finish'));
    }

    public function testShopperGetsTheFinishPageWithoutSessionPaymentEntry(): void
    {
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $redirectUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_unknown'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://shop.test/checkout/finish', $redirectUrl);
        $this->assertTrue($this->logger->hasRecordThatContains('warning', 'has no session payment entry'));
    }

    public function testOrderWithoutTransactionIsRejected(): void
    {
        $order = $this->createOrder();

        $this->expectException(ExpressComponentsException::class);

        $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());
    }

    private function createPaymentFinalizer(): PaymentFinalizer
    {
        return new PaymentFinalizer(
            $this->sessionPaymentRepository,
            $this->sessionWebhookRoute,
            $this->tokenFactory,
            new FakeRouteBuilder(checkoutFinishUrl: 'https://shop.test/checkout/finish', editOrderUrl: 'https://shop.test/account/order/edit'),
            $this->requestStack,
            $this->logger
        );
    }

    private function createOrder(OrderTransactionEntity ...$transactions): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setOrderNumber('10001');
        $order->setTransactions(new OrderTransactionCollection($transactions));

        return $order;
    }

    private function createTransaction(string $id, string $createdAt): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId($id);
        $transaction->setPaymentMethodId('payment-method-1');
        $transaction->setCreatedAt(new \DateTimeImmutable($createdAt));

        return $transaction;
    }
}
