<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Mollie\Exception\TransactionWithoutMollieDataException;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\PaymentLinkGateway;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use Mollie\Shopware\Unit\Transaction\Fake\FakeTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;

#[CoversClass(MollieGateway::class)]
final class MollieGatewayTest extends TestCase
{
    public function testLoadPaymentFromApi(): void
    {
        $fakeClient = new FakeClient('mollieTestId', 'paid');
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();
        $transactionService->createValidStruct();

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new NullLogger()), new NullLogger());

        $context = new Context(new SystemSource());

        $actual = $gateway->getPaymentByTransactionId('test', $context);

        $this->assertInstanceOf(Payment::class, $actual);
        $this->assertSame('mollieTestId',$actual->getId());
        $this->assertSame('paid', $actual->getStatus()->value);
    }

    public function testPaymentIsLoadedByOrderEntity(): void
    {
        $fakeClient = new FakeClient('mollieTestId', 'paid', embed: true);
        $fakeClientFactory = new FakeClientFactory($fakeClient);

        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields([
            'order_id' => 'mollieTestId',
            'transactionReturnUrl' => 'payment/finalize',
        ]);

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new NullLogger()), new NullLogger());

        $context = new Context(new SystemSource());

        $actual = $gateway->getPaymentByTransactionId('test', $context);

        $this->assertInstanceOf(Payment::class, $actual);
        $this->assertSame('mollieTestId',$actual->getId());
        $this->assertSame('paid',$actual->getStatus()->value);
    }

    public function testLoadingPaymentByOrderThrowsException(): void
    {
        $this->expectException(ApiException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields([
            'order_id' => 'mollieTestId',
            'transactionReturnUrl' => 'payment/finalize',
        ]);

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new NullLogger()), new NullLogger());

        $context = new Context(new SystemSource());

        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testTransactionWithoutMollieDataThrowsException(): void
    {
        $this->expectException(TransactionWithoutMollieDataException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new NullLogger()), new NullLogger());

        $context = new Context(new SystemSource());
        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testEmptyMollieOrderIdThrowsException(): void
    {
        $this->expectException(TransactionWithoutMollieDataException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();
        $transactionService->withOrderCustomFields([
            'transactionReturnUrl' => 'payment/finalize',
        ]);
        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new NullLogger()), new NullLogger());

        $context = new Context(new SystemSource());
        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testEmptyTransactionUrlThrowsException(): void
    {
        $this->expectException(TransactionWithoutMollieDataException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();

        $transactionService->withOrderCustomFields([
            'order_id' => 'test',
        ]);
        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new NullLogger()), new NullLogger());

        $context = new Context(new SystemSource());
        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testLoadPaymentThrowsException(): void
    {
        $this->expectException(ApiException::class);

        $fakeClient = new FakeClient();
        $transactionService = new FakeTransactionService();
        $fakeClientFactory = new FakeClientFactory($fakeClient);

        $transactionService->createValidStruct();
        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new NullLogger()), new NullLogger());
        $context = new Context(new SystemSource());
        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testCreatePaymentIsSuccessful(): void
    {
        $fakeClient = new FakeClient('mollieTestId', 'paid');
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new NullLogger()), new NullLogger());

        $createPayment = new CreatePayment('test','test',new Money(10.00,'EUR'));
        $createPayment->setShopwareOrderNumber('10000');
        $payment = $gateway->createPayment($createPayment,Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('mollieTestId',$payment->getId());
        $this->assertSame('paid',$payment->getStatus()->value);
    }

    public function testCreatePaymentHandledApiException(): void
    {
        $this->expectException(ApiException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);

        $transactionService = new FakeTransactionService();
        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new PaymentLinkGateway($fakeClientFactory, new NullLogger()), new NullLogger());

        $createPayment = new CreatePayment('test','test',new Money(10.00,'EUR'));
        $createPayment->setShopwareOrderNumber('10000');
        $gateway->createPayment($createPayment,Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
    }

    public function testGetActivePaymentMethodsReturnsMethodIds(): void
    {
        $body = json_encode([
            '_embedded' => [
                'methods' => [
                    ['id' => 'ideal'],
                    ['id' => 'creditcard'],
                ],
            ],
        ]);
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], (string) $body)]))]);
        $gateway = new MollieGateway(new FakeClientFactory($client), new FakeTransactionService(), new PaymentLinkGateway(new FakeClientFactory($client), new NullLogger()), new NullLogger());

        $result = $gateway->getActivePaymentMethods(new Money(100.0, 'EUR'), 'DE', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame(['ideal', 'creditcard'], $result);
    }

    public function testGetActivePaymentMethodsReturnsEmptyArrayWhenNoMethods(): void
    {
        $body = json_encode(['_embedded' => ['methods' => []]]);
        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(200, [], (string) $body)]))]);
        $gateway = new MollieGateway(new FakeClientFactory($client), new FakeTransactionService(), new PaymentLinkGateway(new FakeClientFactory($client), new NullLogger()), new NullLogger());

        $result = $gateway->getActivePaymentMethods(new Money(100.0, 'EUR'), '', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $this->assertSame([], $result);
    }

    public function testGetActivePaymentMethodsHandlesApiException(): void
    {
        $this->expectException(ApiException::class);

        $client = new Client(['handler' => HandlerStack::create(new MockHandler([new Response(422, [], (string) json_encode(['detail' => 'failed']))]))]);
        $gateway = new MollieGateway(new FakeClientFactory($client), new FakeTransactionService(), new PaymentLinkGateway(new FakeClientFactory($client), new NullLogger()), new NullLogger());

        $gateway->getActivePaymentMethods(new Money(100.0, 'EUR'), 'DE', Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
    }
}
