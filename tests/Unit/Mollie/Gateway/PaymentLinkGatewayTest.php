<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mollie\Shopware\Component\Mollie\CreatePaymentLink;
use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGateway;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\PaymentLink;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(PaymentLinkGateway::class)]
final class PaymentLinkGatewayTest extends TestCase
{
    public function testCreatePaymentLink(): void
    {
        $responseBody = json_encode([
            'id' => 'pl_123',
            '_links' => [
                'paymentLink' => ['href' => 'https://paymentlink.mollie.com/payment/pl_123/'],
            ],
        ]);

        $gateway = $this->buildGateway(new Response(201, [], (string) $responseBody));

        $paymentLink = $gateway->createPaymentLink(new CreatePaymentLink('10000', new Money(10.0, 'EUR')), '10000', 'sales-channel');

        $this->assertInstanceOf(PaymentLink::class, $paymentLink);
        $this->assertSame('pl_123', $paymentLink->getId());
        $this->assertSame('https://paymentlink.mollie.com/payment/pl_123/', $paymentLink->getPaymentLinkUrl());
    }

    public function testListPaymentLinkPayments(): void
    {
        $responseBody = json_encode([
            '_embedded' => [
                'payments' => [
                    ['id' => 'tr_1', 'status' => 'paid'],
                    ['id' => 'tr_2', 'status' => 'open'],
                ],
            ],
        ]);

        $gateway = $this->buildGateway(new Response(200, [], (string) $responseBody));

        $payments = $gateway->listPaymentLinkPayments('pl_123', '10000', 'sales-channel');

        $this->assertCount(2, $payments);
        $this->assertNotNull($payments->get('tr_1'));
        $this->assertSame('paid', $payments->get('tr_1')->getStatus()->value);
    }

    public function testApiErrorIsConverted(): void
    {
        $this->expectException(ApiException::class);

        $errorBody = json_encode(['title' => 'Unprocessable Entity', 'detail' => 'invalid', 'field' => 'amount']);
        $gateway = $this->buildGateway(new Response(422, [], (string) $errorBody));

        $gateway->createPaymentLink(new CreatePaymentLink('10000', new Money(10.0, 'EUR')), '10000', 'sales-channel');
    }

    private function buildGateway(Response $response): PaymentLinkGateway
    {
        $mockHandler = new MockHandler([$response]);
        $client = new Client(['handler' => HandlerStack::create($mockHandler)]);
        $clientFactory = new FakeClientFactory($client);

        return new PaymentLinkGateway($clientFactory, new NullLogger());
    }
}
