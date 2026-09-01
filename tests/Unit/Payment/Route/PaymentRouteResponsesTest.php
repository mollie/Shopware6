<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Route;

use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebhookResponse::class)]
final class PaymentRouteResponsesTest extends TestCase
{
    public function testWebhookResponseStoresPayment(): void
    {
        $payment = new Payment('pay-1');

        $response = new WebhookResponse($payment);

        $this->assertSame($payment, $response->getPayment());
    }
}
