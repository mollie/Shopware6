<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie;

use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Order;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Mollie\PaymentStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Order::class)]
final class OrderTest extends TestCase
{
    public function testResponseWithoutAnEmbeddedPaymentIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        Order::createFromClientResponse(['id' => 'ord_test']);
    }

    public function testCanCreateFromClientResponse(): void
    {
        $order = Order::createFromClientResponse($this->responseBody());

        $this->assertSame('ord_test', $order->getId());
        $this->assertSame('https://mollie.com/checkout', $order->getCheckoutUrl());
        $this->assertSame(PaymentStatus::PAID, $order->getStatus());
        $this->assertSame('tr_test', $order->getPayment()->getId());
    }

    public function testUnknownStatusStaysUnset(): void
    {
        $body = $this->responseBody();
        $body['status'] = 'something-new';

        $this->assertNull(Order::createFromClientResponse($body)->getStatus());
    }

    public function testLinesAreHydratedFromTheResponse(): void
    {
        $body = $this->responseBody();
        $body['lines'] = [
            ['id' => 'odl_1', 'name' => 'Product A', 'quantity' => 2, 'totalAmount' => ['value' => '20.00', 'currency' => 'EUR']],
        ];

        $lines = Order::createFromClientResponse($body)->getLines();

        $this->assertCount(1, $lines);
        $this->assertSame('Product A', $lines->first()->getDescription());
    }

    public function testRefundsAreHydratedFromTheResponse(): void
    {
        $body = $this->responseBody();
        $body['_embedded']['refunds'] = [[
            'id' => 're_1',
            'paymentId' => 'tr_test',
            'status' => 'pending',
            'amount' => ['value' => '10.00', 'currency' => 'EUR'],
            'description' => 'Refund',
            'createdAt' => '2026-01-15T10:00:00+00:00',
        ]];

        $refunds = Order::createFromClientResponse($body)->getRefunds();

        $this->assertCount(1, $refunds);
    }

    public function testCapturedAmountIsReadFromTheResponse(): void
    {
        $body = $this->responseBody();
        $body['amountCaptured'] = ['value' => '19.00', 'currency' => 'EUR'];

        $this->assertEquals(new Money(19.00, 'EUR'), Order::createFromClientResponse($body)->getAmountCaptured());
    }

    public function testCapturedAmountIsUnknownWhenMollieDoesNotReportIt(): void
    {
        $this->assertNull(Order::createFromClientResponse($this->responseBody())->getAmountCaptured());
    }

    public function testOrderWithoutPaymentCannotBeAskedForOne(): void
    {
        $order = new Order('ord_test', 'https://mollie.com/checkout');

        $this->expectException(\RuntimeException::class);
        $order->getPayment();
    }

    public function testWithPaymentLeavesTheOriginalOrderUntouched(): void
    {
        $order = new Order('ord_test', 'https://mollie.com/checkout');

        $withPayment = $order->withPayment(new Payment('tr_test'));

        $this->assertSame('tr_test', $withPayment->getPayment()->getId());
        $this->assertNotSame($order, $withPayment);
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBody(): array
    {
        return [
            'id' => 'ord_test',
            'status' => 'paid',
            '_links' => ['checkout' => ['href' => 'https://mollie.com/checkout']],
            '_embedded' => ['payments' => [['id' => 'tr_test', 'status' => 'paid']]],
        ];
    }
}
