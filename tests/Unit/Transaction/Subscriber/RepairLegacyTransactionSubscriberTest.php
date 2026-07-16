<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Transaction\Subscriber;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Transaction\Event\RepairLegacyTransactionEvent;
use Mollie\Shopware\Component\Transaction\Subscriber\RepairLegacyTransactionSubscriber;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class RepairLegacyTransactionSubscriberTest extends TestCase
{
    public function testTransactionIsRepairedWhenExtensionIsMissing(): void
    {
        $transaction = $this->buildTransaction();
        $gateway = new FakeGateway();
        $repairedPayment = new Payment('tr_repaired');
        $gateway->withRepairResult($repairedPayment);

        $subscriber = new RepairLegacyTransactionSubscriber($gateway, new FakeLogger());
        $subscriber->onRepairLegacyTransaction($this->buildEvent($transaction));

        $this->assertSame(1, $gateway->getRepairCallCount());
        $this->assertSame($repairedPayment, $transaction->getExtension(Mollie::EXTENSION));
        $this->assertSame($transaction, $repairedPayment->getShopwareTransaction());
    }

    public function testRepairIsSkippedWhenExtensionAlreadyPresent(): void
    {
        $existingPayment = new Payment('tr_existing');
        $transaction = $this->buildTransaction();
        $transaction->addExtension(Mollie::EXTENSION, $existingPayment);

        $gateway = new FakeGateway();

        $subscriber = new RepairLegacyTransactionSubscriber($gateway, new FakeLogger());
        $subscriber->onRepairLegacyTransaction($this->buildEvent($transaction));

        $this->assertSame(0, $gateway->getRepairCallCount());
        $this->assertSame($existingPayment, $transaction->getExtension(Mollie::EXTENSION));
    }

    public function testNoExtensionIsAddedWhenRepairReturnsNull(): void
    {
        $transaction = $this->buildTransaction();
        $gateway = new FakeGateway();
        $gateway->withRepairResult(null);

        $subscriber = new RepairLegacyTransactionSubscriber($gateway, new FakeLogger());
        $subscriber->onRepairLegacyTransaction($this->buildEvent($transaction));

        $this->assertSame(1, $gateway->getRepairCallCount());
        $this->assertFalse($transaction->hasExtension(Mollie::EXTENSION));
    }

    public function testRepairExceptionIsLoggedAndSwallowed(): void
    {
        $transaction = $this->buildTransaction();
        $gateway = new FakeGateway();
        $gateway->withRepairThrowing();
        $logger = new FakeLogger();

        $subscriber = new RepairLegacyTransactionSubscriber($gateway, $logger);
        $subscriber->onRepairLegacyTransaction($this->buildEvent($transaction));

        $this->assertFalse($transaction->hasExtension(Mollie::EXTENSION));
        $this->assertTrue($logger->hasRecordThatContains('error', 'Failed to repair legacy transaction'));
    }

    private function buildTransaction(): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');

        return $transaction;
    }

    private function buildEvent(OrderTransactionEntity $transaction): RepairLegacyTransactionEvent
    {
        $order = new OrderEntity();
        $order->setId('order-1');
        $order->setOrderNumber('10001');

        return new RepairLegacyTransactionEvent($transaction, $order, Context::createDefaultContext());
    }
}
