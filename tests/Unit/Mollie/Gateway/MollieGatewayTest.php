<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\CreatePayment;
use Mollie\Shopware\Component\Mollie\Gateway\ApiException;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Gateway\TransactionWithoutMollieDataException;
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

        $gateway = new MollieGateway($fakeClientFactory,$transactionService, new NullLogger());

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

        $gateway = new MollieGateway($fakeClientFactory, $transactionService, new NullLogger());

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

        $gateway = new MollieGateway($fakeClientFactory,$transactionService, new NullLogger());

        $context = new Context(new SystemSource());

        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testTransactionWithoutMollieDataThrowsException(): void
    {
        $this->expectException(TransactionWithoutMollieDataException::class);
        $fakeClient = new FakeClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();

        $gateway = new MollieGateway($fakeClientFactory,$transactionService, new NullLogger());

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
        $gateway = new MollieGateway($fakeClientFactory,$transactionService, new NullLogger());

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
        $gateway = new MollieGateway($fakeClientFactory,$transactionService, new NullLogger());

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
        $gateway = new MollieGateway($fakeClientFactory,$transactionService, new NullLogger());
        $context = new Context(new SystemSource());
        $gateway->getPaymentByTransactionId('test', $context);
    }

    public function testCreatePaymentIsSuccessful(): void
    {
        $fakeClient = new FakeClient('mollieTestId', 'paid');
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $transactionService = new FakeTransactionService();

        $gateway = new MollieGateway($fakeClientFactory,$transactionService, new NullLogger());

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
        $gateway = new MollieGateway($fakeClientFactory,$transactionService, new NullLogger());

        $createPayment = new CreatePayment('test','test',new Money(10.00,'EUR'));
        $createPayment->setShopwareOrderNumber('10000');
        $gateway->createPayment($createPayment,Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
    }
}
