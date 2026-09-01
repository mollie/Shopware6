<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FailureMode;

use Mollie\Shopware\Component\FailureMode\PaymentPageFailedEvent;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * The event other plugins listen to when a payment page could not be reached. What it exposes is
 * the contract those listeners are written against.
 */
#[CoversClass(PaymentPageFailedEvent::class)]
final class PaymentPageFailedEventTest extends TestCase
{
    public function testAListenerCanReachTheTransactionOrderAndPayment(): void
    {
        $order = $this->order();
        $payment = new Payment('tr_1');

        $event = new PaymentPageFailedEvent('transaction-1', $order, $payment, new FakeSalesChannelContext());

        $this->assertSame('transaction-1', $event->getTransactionId());
        $this->assertSame($order, $event->getOrder());
        $this->assertSame($payment, $event->getPayment());
    }

    public function testTheSalesChannelIsTakenFromTheContextTheCustomerCheckedOutIn(): void
    {
        $salesChannelContext = new FakeSalesChannelContext('sales-channel-1');

        $event = new PaymentPageFailedEvent('transaction-1', $this->order(), new Payment('tr_1'), $salesChannelContext);

        $this->assertSame($salesChannelContext, $event->getSalesChannelContext());
        $this->assertSame('sales-channel-1', $event->getSalesChannelId());
    }

    /**
     * A listener that wants to send the customer to Mollie anyway needs the checkout url of the
     * payment, not one it builds itself.
     */
    public function testTheRedirectUrlIsTheCheckoutUrlOfThePayment(): void
    {
        $payment = new Payment('tr_1');
        $payment->setCheckoutUrl('https://mollie.test/checkout');

        $event = new PaymentPageFailedEvent('transaction-1', $this->order(), $payment, new FakeSalesChannelContext());

        $this->assertSame('https://mollie.test/checkout', $event->getRedirectUrl());
    }

    private function order(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId('order-1');

        return $order;
    }
}
