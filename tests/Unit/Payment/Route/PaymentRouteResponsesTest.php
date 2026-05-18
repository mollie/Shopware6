<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Route;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use Mollie\Shopware\Component\Payment\Route\ReturnRouteResponse;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

#[CoversClass(WebhookResponse::class)]
#[CoversClass(ReturnRouteResponse::class)]
final class PaymentRouteResponsesTest extends TestCase
{
    public function testWebhookResponseStoresPayment(): void
    {
        $payment = new Payment('pay-1');

        $response = new WebhookResponse($payment);

        $this->assertSame($payment, $response->getPayment());
    }

    public function testReturnRouteResponseExposesPaymentData(): void
    {
        $payment = new Payment('pay-2');
        $payment->setStatus(PaymentStatus::OPEN);
        $payment->setFinalizeUrl('https://example.com/finalize');
        $tx = new OrderTransactionEntity();
        $tx->setOrderId('order-1');
        $payment->setShopwareTransaction($tx);

        $response = new ReturnRouteResponse($payment);

        $this->assertSame($payment, $response->getPayment());
        $this->assertSame('pay-2', $response->getPaymentId());
        $this->assertSame(PaymentStatus::OPEN, $response->getPaymentStatus());
        $this->assertSame('https://example.com/finalize', $response->getFinalizeUrl());
    }
}
