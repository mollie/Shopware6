<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\ExpressComponents\ExpressComponentsException;
use Mollie\Shopware\Component\Payment\ExpressComponents\FinishUrls;
use Mollie\Shopware\Component\Payment\ExpressComponents\PaymentFinalizer;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeOrderTransactionRepository;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use Mollie\Shopware\Unit\Mollie\Fake\FakeRouteBuilder;
use Mollie\Shopware\Unit\Payment\ExpressComponents\Fake\FakeSessionPaymentRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(PaymentFinalizer::class)]
final class PaymentFinalizerTest extends TestCase
{
    private FakeSessionPaymentRepository $sessionPaymentRepository;
    private FakeOrderTransactionRepository $orderTransactionRepository;
    private RequestStack $requestStack;
    private FakeLogger $logger;

    protected function setUp(): void
    {
        $this->sessionPaymentRepository = new FakeSessionPaymentRepository(FakeSessionPaymentRepository::entry('webhook-id', 'ses_1'));
        $this->orderTransactionRepository = new FakeOrderTransactionRepository();
        $this->orderTransactionRepository->withTransaction($this->createTransaction('transaction-1', '2026-11-03 10:00:00', OrderTransactionStates::STATE_PAID));
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

    public function testFinishContinuesWithoutSessionPaymentEntry(): void
    {
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $finishUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_unknown'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://shop.test/checkout/finish', $finishUrl);
        $this->assertTrue($this->logger->hasRecordThatContains('warning', 'has no session payment entry'));
    }

    public function testFinishUrlOfTheRequestIsReturnedForAPaidPayment(): void
    {
        $this->requestStack->push(new Request([FinishUrls::FINISH_URL_PARAMETER => 'https://pwa.test/finish/' . FinishUrls::ORDER_ID_PLACEHOLDER]));
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $finishUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://pwa.test/finish/order-1', $finishUrl);
    }

    public function testStorefrontFinishPageIsReturnedForAPaidPaymentWithoutFinishUrl(): void
    {
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $finishUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://shop.test/checkout/finish', $finishUrl);
    }

    #[DataProvider('failedStateProvider')]
    public function testEditOrderPageIsReturnedForAFailedPayment(string $state): void
    {
        $this->orderTransactionRepository->withTransaction($this->createTransaction('transaction-1', '2026-11-03 10:00:00', $state));
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $finishUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://shop.test/account/order/edit', $finishUrl);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function failedStateProvider(): array
    {
        return [
            'failed' => [OrderTransactionStates::STATE_FAILED],
            'cancelled' => [OrderTransactionStates::STATE_CANCELLED],
        ];
    }

    public function testErrorUrlOfTheRequestIsReturnedForAFailedPayment(): void
    {
        $this->requestStack->push(new Request([FinishUrls::ERROR_URL_PARAMETER => 'https://pwa.test/error/' . FinishUrls::ORDER_ID_PLACEHOLDER]));
        $this->orderTransactionRepository->withTransaction($this->createTransaction('transaction-1', '2026-11-03 10:00:00', OrderTransactionStates::STATE_FAILED));
        $order = $this->createOrder($this->createTransaction('transaction-1', '2026-11-03 10:00:00'));

        $finishUrl = $this->createPaymentFinalizer()->finalize(new Session('ses_1'), $order, new FakeSalesChannelContext());

        $this->assertSame('https://pwa.test/error/order-1', $finishUrl);
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
            $this->orderTransactionRepository,
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

    private function createTransaction(string $id, string $createdAt, ?string $state = null): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId($id);
        $transaction->setCreatedAt(new \DateTimeImmutable($createdAt));
        if ($state !== null) {
            $stateMachineState = new StateMachineStateEntity();
            $stateMachineState->setId($state);
            $stateMachineState->setTechnicalName($state);
            $transaction->setStateMachineState($stateMachineState);
        }

        return $transaction;
    }
}
