<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Transaction;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentMethod;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Transaction\TransactionService;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeOrderTransactionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(TransactionService::class)]
final class TransactionServiceTest extends TestCase
{
    public function testOrderCustomFieldsContainJtlMappedKeys(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $service = new TransactionService($repository, new FakeLogger());

        $service->savePaymentExtension('transactionId', $this->buildOrder(), $this->buildPayment(), new Context(new SystemSource()));

        $upsert = $repository->getUpserts()[0];
        $orderExtension = $upsert['order']['customFields'][Mollie::EXTENSION];

        $this->assertSame('ord_orderId', $orderExtension['order_id']);
        $this->assertSame('tr_paymentId', $orderExtension['payment_id']);
        $this->assertSame('paypal_thirdParty', $orderExtension['third_party_payment_id']);
    }

    public function testTransactionCustomFieldsDoNotContainJtlMappedKeys(): void
    {
        $repository = new FakeOrderTransactionRepository();
        $service = new TransactionService($repository, new FakeLogger());

        $service->savePaymentExtension('transactionId', $this->buildOrder(), $this->buildPayment(), new Context(new SystemSource()));

        $upsert = $repository->getUpserts()[0];
        $transactionExtension = $upsert['customFields'][Mollie::EXTENSION];

        $this->assertArrayNotHasKey('order_id', $transactionExtension);
        $this->assertArrayNotHasKey('payment_id', $transactionExtension);
        $this->assertArrayNotHasKey('third_party_payment_id', $transactionExtension);
    }

    private function buildPayment(): Payment
    {
        $payment = new Payment('tr_paymentId');
        $payment->setOrderId('ord_orderId');
        $payment->setThirdPartyPaymentId('paypal_thirdParty');
        $payment->setMethod(PaymentMethod::PAYPAL);
        $payment->setStatus(PaymentStatus::PAID);

        return $payment;
    }

    private function buildOrder(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('shopwareOrderId');
        $order->setOrderNumber('10000');
        $order->setSalesChannelId('salesChannelId');

        return $order;
    }
}
