<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscriber;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Mollie;
use Mollie\Shopware\Subscriber\OrderTransactionSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;

#[CoversClass(OrderTransactionSubscriber::class)]
final class OrderTransactionSubscriberTest extends TestCase
{
    public function testPaymentDetailsAreHydratedFromCustomFields(): void
    {
        $transaction = $this->buildTransaction([
            'id' => 'tr_xxx',
            'method' => 'paypal',
            'paypalPayerId' => 'PAYER-1',
            'creditCardLabel' => 'VISA',
            'creditCardNumber' => '1234',
            'creditCardHolder' => 'John Doe',
            'bankAccount' => 'NL12345',
            'bankName' => 'Test Bank',
        ]);

        $subscriber = new OrderTransactionSubscriber();
        $subscriber->onOrderTransaction($this->buildEvent([$transaction]));

        $payment = $transaction->getExtension(Mollie::EXTENSION);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(['payerId' => 'PAYER-1'], $payment->getPaypalDetails());
        $this->assertSame(
            ['label' => 'VISA', 'number' => '1234', 'holder' => 'John Doe'],
            $payment->getCreditCardDetails()
        );
        $this->assertNotNull($payment->getBankTransferDetails());
    }

    public function testNoExtensionIsAddedWithoutPaymentId(): void
    {
        $transaction = $this->buildTransaction(['method' => 'paypal']);

        $subscriber = new OrderTransactionSubscriber();
        $subscriber->onOrderTransaction($this->buildEvent([$transaction]));

        $this->assertFalse($transaction->hasExtension(Mollie::EXTENSION));
    }

    /**
     * @param array<string, mixed> $mollieCustomFields
     */
    private function buildTransaction(array $mollieCustomFields): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-1');
        $transaction->setCustomFields([Mollie::EXTENSION => $mollieCustomFields]);

        return $transaction;
    }

    /**
     * @param list<OrderTransactionEntity> $transactions
     *
     * @return EntityLoadedEvent<OrderTransactionEntity>
     */
    private function buildEvent(array $transactions): EntityLoadedEvent
    {
        return new EntityLoadedEvent(
            new OrderTransactionDefinition(),
            $transactions,
            Context::createDefaultContext()
        );
    }
}
