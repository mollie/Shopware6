<?php

declare(strict_types=1);

namespace Mollie\Shopware\Unit\Order\Admin;

use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Order\Admin\OrderPaymentRecovery;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeOrderRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

#[CoversClass(OrderPaymentRecovery::class)]
final class OrderPaymentRecoveryTest extends TestCase
{
    private FakeOrderRepository $orderRepository;

    private OrderPaymentRecovery $recovery;

    protected function setUp(): void
    {
        $this->orderRepository = new FakeOrderRepository();
        $this->recovery = new OrderPaymentRecovery($this->orderRepository);
    }

    public function testRestoreRebuildsPaymentFromLegacyCustomFieldsAndPersistsIt(): void
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setCustomFields([
            Mollie::EXTENSION => [
                'payment_id' => 'tr_1',
                'order_id' => 'ord_1',
                'payment_method' => PaymentMethod::PAYPAL->value,
                'third_party_payment_id' => 'pp_1',
                'molliePaymentUrl' => 'https://mollie.example/checkout',
                'creditCardLabel' => 'Visa',
                'creditCardNumber' => '1234',
                'creditCardHolder' => 'John Doe',
            ],
        ]);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('tx-1');

        $payment = $this->recovery->restore($order, $transaction, Context::createDefaultContext());

        self::assertNotNull($payment);
        self::assertSame('tr_1', $payment->getId());
        self::assertSame('ord_1', $payment->getOrderId());
        self::assertSame(PaymentMethod::PAYPAL, $payment->getMethod());
        self::assertSame('pp_1', $payment->getThirdPartyPaymentId());
        self::assertSame('https://mollie.example/checkout', $payment->getCheckoutUrl());
        self::assertSame('Visa', $payment->getCreditCardLabel());
        self::assertSame('1234', $payment->getCreditCardNumber());
        self::assertSame('John Doe', $payment->getCreditCardHolder());

        // The rebuilt payment is written back onto the transaction.
        self::assertSame(1, $this->orderRepository->getUpsertCount());
        $upsert = $this->orderRepository->getUpserts()[0];
        self::assertSame('order-1', $upsert['id']);
        self::assertSame('tx-1', $upsert['transactions'][0]['id']);
        self::assertArrayHasKey(Mollie::EXTENSION, $upsert['transactions'][0]['customFields']);
    }

    public function testRestoreReturnsNullWhenNoMollieDataPresent(): void
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setCustomFields([]);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('tx-1');

        $payment = $this->recovery->restore($order, $transaction, Context::createDefaultContext());

        self::assertNull($payment);
        self::assertSame(0, $this->orderRepository->getUpsertCount());
    }
}
