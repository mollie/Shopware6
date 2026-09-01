<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\Gateway\ApplePayGateway;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClient;
use Mollie\Shopware\Unit\Mollie\Fake\FakeClientFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ApplePayGateway::class)]
final class ApplePayGatewayTest extends TestCase
{
    #[DataProvider('domainProvider')]
    public function testRequestSessionSendsHostWithoutProtocolOrPath(string $domain, string $expected): void
    {
        $fakeClient = new FakeClient('session-id');
        $gateway = new ApplePayGateway(new FakeClientFactory($fakeClient), new NullLogger());

        $gateway->requestSession($domain, 'https://apple.com/validate', 'sales-channel-id');

        $this->assertSame($expected, $fakeClient->getLastPostOptions()['form_params']['domain']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function domainProvider(): array
    {
        return [
            'https with path' => ['https://example-shop.com/it', 'example-shop.com'],
            'http with path' => ['http://example-shop.com/it', 'example-shop.com'],
            'https without path' => ['https://example-shop.com', 'example-shop.com'],
            'bare domain' => ['example-shop.com', 'example-shop.com'],
            'bare domain with path' => ['example-shop.com/it', 'example-shop.com'],
        ];
    }
}
