<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Payment\DuplicatePaymentReconciler;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeOrderTransactionRepository;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use Mollie\Shopware\Unit\Payment\Fake\FakeRefundGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(DuplicatePaymentReconciler::class)]
final class DuplicatePaymentReconcilerTest extends TestCase
{
    public function testPaidDuplicateIsRefundedAndFlaggedReconciled(): void
    {
        $live = new Payment('tr_old');
        $live->setStatus(PaymentStatus::PAID);
        $live->setAmount(new Money(10.0, 'EUR'));

        $gateway = new FakeGateway('', $live);
        $refundGateway = new FakeRefundGateway();
        $transactionRepository = new FakeOrderTransactionRepository();

        $reconciler = new DuplicatePaymentReconciler($gateway, $refundGateway, $transactionRepository, new NullLogger());

        $order = $this->buildOrder(
            $this->buildTransaction('current', 'tr_current'),
            $this->buildTransaction('old', 'tr_old'),
        );

        $reconciler->reconcile($order, 'current', new Context(new SystemSource()));

        $this->assertCount(1, $refundGateway->getCreatedRefunds());
        $this->assertSame([], $gateway->getCancelledPaymentIds());

        $upserts = $transactionRepository->getUpserts();
        $this->assertCount(1, $upserts);
        $this->assertSame('old', $upserts[0]['id']);
        $this->assertTrue($upserts[0]['customFields'][Mollie::EXTENSION]['reconciled']);
    }

    public function testCancelableDuplicateIsCancelled(): void
    {
        $live = new Payment('tr_old');
        $live->setStatus(PaymentStatus::OPEN);
        $live->setCancelable(true);

        $gateway = new FakeGateway('', $live);
        $refundGateway = new FakeRefundGateway();
        $transactionRepository = new FakeOrderTransactionRepository();

        $reconciler = new DuplicatePaymentReconciler($gateway, $refundGateway, $transactionRepository, new NullLogger());

        $order = $this->buildOrder(
            $this->buildTransaction('current', 'tr_current'),
            $this->buildTransaction('old', 'tr_old'),
        );

        $reconciler->reconcile($order, 'current', new Context(new SystemSource()));

        $this->assertSame(['tr_old'], $gateway->getCancelledPaymentIds());
        $this->assertCount(0, $refundGateway->getCreatedRefunds());
    }

    public function testOpenOrdersApiDuplicateIsCancelledAsOrder(): void
    {
        $live = new Payment('tr_old');
        $live->setStatus(PaymentStatus::OPEN);

        $gateway = new FakeGateway('', $live);
        $refundGateway = new FakeRefundGateway();
        $transactionRepository = new FakeOrderTransactionRepository();

        $reconciler = new DuplicatePaymentReconciler($gateway, $refundGateway, $transactionRepository, new NullLogger());

        $order = $this->buildOrder(
            $this->buildTransaction('current', 'tr_current'),
            $this->buildTransaction('old', 'tr_old', 'ord_old'),
        );

        $reconciler->reconcile($order, 'current', new Context(new SystemSource()));

        $this->assertSame(['ord_old'], $gateway->getCancelledOrderIds());
        $this->assertSame([], $gateway->getCancelledPaymentIds());
    }

    public function testAlreadyReconciledDuplicateIsSkipped(): void
    {
        $live = new Payment('tr_old');
        $live->setStatus(PaymentStatus::PAID);
        $live->setAmount(new Money(10.0, 'EUR'));

        $gateway = new FakeGateway('', $live);
        $refundGateway = new FakeRefundGateway();
        $transactionRepository = new FakeOrderTransactionRepository();

        $reconciler = new DuplicatePaymentReconciler($gateway, $refundGateway, $transactionRepository, new NullLogger());

        $order = $this->buildOrder(
            $this->buildTransaction('current', 'tr_current'),
            $this->buildTransaction('old', 'tr_old', '', true),
        );

        $reconciler->reconcile($order, 'current', new Context(new SystemSource()));

        $this->assertCount(0, $refundGateway->getCreatedRefunds());
        $this->assertCount(0, $transactionRepository->getUpserts());
    }

    private function buildOrder(OrderTransactionEntity ...$transactions): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-id');
        $order->setOrderNumber('10001');
        $order->setSalesChannelId('sales-channel-id');
        $order->setTransactions(new OrderTransactionCollection($transactions));

        return $order;
    }

    private function buildTransaction(string $id, string $paymentId, string $mollieOrderId = '', bool $reconciled = false): OrderTransactionEntity
    {
        $extension = new Payment($paymentId);
        $extension->setOrderId($mollieOrderId);
        $extension->setReconciled($reconciled);

        $transaction = new OrderTransactionEntity();
        $transaction->setId($id);
        $transaction->addExtension(Mollie::EXTENSION, $extension);
        $transaction->setCustomFields([
            Mollie::EXTENSION => [
                'id' => $paymentId,
                'orderId' => $mollieOrderId,
                'reconciled' => $reconciled,
            ],
        ]);

        return $transaction;
    }
}
