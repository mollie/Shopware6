<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\FinishUrls;
use Mollie\Shopware\Component\Payment\ExpressComponents\PaymentFinalizer;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionPayment;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionPaymentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(PaymentFinalizer::class)]
final class PaymentFinalizerTest extends TestCase
{
    private FakeSessionPaymentRepository $sessionPaymentRepository;
    private RequestStack $requestStack;
    private FakeLogger $logger;

    protected function setUp(): void
    {
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository(new SessionPayment('webhook-id', 'ses_1', null, null));
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

        $this->assertSame('transaction-new', $this->sessionPaymentRepository->findById('webhook-id')?->getTransactionId());
    }

    public function testFinishContinuesWithoutSessionPaymentEntry(): void
    {
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $finishUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_unknown'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://shop.test/checkout/finish', $finishUrl);
        $this->assertTrue($this->logger->hasRecordThatContains('warning', 'has no session payment entry'));
    }

    public function testFinishUrlOfTheRequestIsReturned(): void
    {
        $this->requestStack->push(new Request([FinishUrls::FINISH_URL_PARAMETER => 'https://pwa.test/finish/' . FinishUrls::ORDER_ID_PLACEHOLDER]));
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $finishUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://pwa.test/finish/order-1', $finishUrl);
    }

    public function testStorefrontFinishPageIsReturnedWithoutFinishUrl(): void
    {
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $finishUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://shop.test/checkout/finish', $finishUrl);
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
            new FakeRouteBuilder(checkoutFinishUrl: 'https://shop.test/checkout/finish'),
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
        $transaction->setCreatedAt(new \DateTimeImmutable($createdAt));

        return $transaction;
    }
}
