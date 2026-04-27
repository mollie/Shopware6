<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\FailureMode;

use Mollie\Shopware\Component\FailureMode\PaymentPageFailedEvent;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Unit\Fake\FakeSalesChannelContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;

#[CoversClass(PaymentPageFailedEvent::class)]
final class PaymentPageFailedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $payment = new Payment('tr_failed');
        $payment->setCheckoutUrl('https://checkout.mollie.com/pay/tr_failed');

        $order = new OrderEntity();
        $order->setId('order-abc');

        $salesChannelContext = new FakeSalesChannelContext('sc-123');

        $event = new PaymentPageFailedEvent('tx-001', $order, $payment, $salesChannelContext);

        $this->assertSame('tx-001', $event->getTransactionId());
        $this->assertSame($order, $event->getOrder());
        $this->assertSame($payment, $event->getPayment());
        $this->assertSame($salesChannelContext, $event->getSalesChannelContext());
        $this->assertSame('sc-123', $event->getSalesChannelId());
        $this->assertSame('https://checkout.mollie.com/pay/tr_failed', $event->getRedirectUrl());
    }
}
