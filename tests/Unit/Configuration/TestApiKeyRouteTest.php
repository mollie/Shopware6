<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Configuration;

use Mollie\Shopware\Component\Configuration\Route\TestApiKeyRoute;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(TestApiKeyRoute::class)]
final class TestApiKeyRouteTest extends TestCase
{
    public function testValidKeysReturnValidTrue(): void
    {
        $gateway = new FakeGateway();
        $gateway->withValidApiKey('live_key_abc');
        $gateway->withValidApiKey('test_key_xyz');

        $controller = new TestApiKeyRoute($gateway);

        $request = new Request(request: ['liveApiKey' => 'live_key_abc', 'testApiKey' => 'test_key_xyz']);

        $response = $controller->testApiKeys($request);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertCount(2, $body['results']);
        $this->assertSame('live_key_abc', $body['results'][0]['key']);
        $this->assertSame('live', $body['results'][0]['mode']);
        $this->assertTrue($body['results'][0]['valid']);
        $this->assertSame('test_key_xyz', $body['results'][1]['key']);
        $this->assertSame('test', $body['results'][1]['mode']);
        $this->assertTrue($body['results'][1]['valid']);
    }

    public function testInvalidKeysReturnValidFalse(): void
    {
        $gateway = new FakeGateway();

        $controller = new TestApiKeyRoute($gateway);

        $request = new Request(request: ['liveApiKey' => 'bad_live_key', 'testApiKey' => 'bad_test_key']);

        $response = $controller->testApiKeys($request);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertFalse($body['results'][0]['valid']);
        $this->assertFalse($body['results'][1]['valid']);
    }

    public function testEmptyKeysReturnValidFalse(): void
    {
        $gateway = new FakeGateway();

        $controller = new TestApiKeyRoute($gateway);

        $request = new Request();

        $response = $controller->testApiKeys($request);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertSame('', $body['results'][0]['key']);
        $this->assertFalse($body['results'][0]['valid']);
        $this->assertSame('', $body['results'][1]['key']);
        $this->assertFalse($body['results'][1]['valid']);
    }

    public function testOneLiveKeyValidOneTestKeyInvalid(): void
    {
        $gateway = new FakeGateway();
        $gateway->withValidApiKey('valid_live_key');

        $controller = new TestApiKeyRoute($gateway);

        $request = new Request(request: ['liveApiKey' => 'valid_live_key', 'testApiKey' => 'invalid_test_key']);

        $response = $controller->testApiKeys($request);
        $body = json_decode((string) $response->getContent(), true);

        $this->assertTrue($body['results'][0]['valid']);
        $this->assertFalse($body['results'][1]['valid']);
    }
}
