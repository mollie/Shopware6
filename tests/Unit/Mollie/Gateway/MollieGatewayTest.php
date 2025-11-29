<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use GuzzleHttp\Psr7\Response;
use Mollie\Shopware\Component\Mollie\Gateway\ApiException;
use Mollie\Shopware\Component\Mollie\Gateway\MollieGateway;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Unit\Mollie\Fake\FakeApiExceptionClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use Mollie\Shopware\Unit\Mollie\Fake\FakeOrderTransactionRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(MollieGateway::class)]
final class MollieGatewayTest extends TestCase
{
    public function testLoadPaymentFromApi(): void
    {
        $response = new Response(body: json_encode(['id' => 'test', 'method' => 'fake', 'status' => 'open']));
        $fakeClient = new FakeClient($response);
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $fakeOrderTransactionRepository = new FakeOrderTransactionRepository();
        $gateway = new MollieGateway($fakeClientFactory,$fakeOrderTransactionRepository,new NullLogger());

        $actual = $gateway->getPayment('test','test');

        $this->assertInstanceOf(Payment::class,$actual);
    }

    public function testLoadPaymentThrowsException(): void
    {
        $this->expectException(ApiException::class);

        $fakeClient = new FakeApiExceptionClient();
        $fakeClientFactory = new FakeClientFactory($fakeClient);
        $fakeOrderTransactionRepository = new FakeOrderTransactionRepository();
        $gateway = new MollieGateway($fakeClientFactory,$fakeOrderTransactionRepository,new NullLogger());

        $gateway->getPayment('test','test');
    }
}
