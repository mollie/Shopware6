<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use GuzzleHttp\Psr7\Uri;
use Kiener\MolliePayments\MolliePayments;
use Mollie\Shopware\Component\Mollie\Gateway\ApiKeyException;
use Mollie\Shopware\Component\Mollie\Gateway\ClientFactory;
use Mollie\Shopware\Component\Settings\Struct\ApiSettings;
use Mollie\Shopware\Unit\Logger\FakeSettingsService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Test\TestDefaults;

final class ClientFactoryTest extends TestCase
{
    public function testClientIsCreatedWithTestApiKey(): void
    {
        $shopwareVersion = '6.7.4.0';
        $key = 'test_key';
        $apiSettings = new ApiSettings($key, '', true);
        $fakeSettings = new FakeSettingsService(apiSettings: $apiSettings);
        $factory = new ClientFactory($fakeSettings, $shopwareVersion);

        $client = $factory->create(TestDefaults::SALES_CHANNEL);
        $headers = $client->getConfig('headers');
        /** @var Uri $baseUri */
        $baseUri = $client->getConfig('base_uri');

        $expectedUserAgent = sprintf('Shopware/%s MollieShopware6/%s', $shopwareVersion, MolliePayments::PLUGIN_VERSION);
        $expectedAuthorization = 'Bearer ' . $key;
        $expectedUrl = 'https://api.mollie.com/v2/';
        $this->assertSame($expectedAuthorization, $headers['Authorization']);
        $this->assertSame($expectedUserAgent, $headers['User-Agent']);
        $this->assertSame($expectedUrl, (string) $baseUri);
    }

    public function testLiveKeyIsUsedInLiveMode(): void
    {
        $shopwareVersion = '6.7.4.0';
        $key = 'live_key';
        $apiSettings = new ApiSettings('test_key', $key, false);
        $fakeSettings = new FakeSettingsService(apiSettings: $apiSettings);
        $factory = new ClientFactory($fakeSettings, $shopwareVersion);

        $client = $factory->create(TestDefaults::SALES_CHANNEL);
        $headers = $client->getConfig('headers');
        /** @var Uri $baseUri */
        $baseUri = $client->getConfig('base_uri');

        $expectedUserAgent = sprintf('Shopware/%s MollieShopware6/%s', $shopwareVersion, MolliePayments::PLUGIN_VERSION);
        $expectedAuthorization = 'Bearer ' . $key;
        $expectedUrl = 'https://api.mollie.com/v2/';
        $this->assertSame($expectedAuthorization, $headers['Authorization']);
        $this->assertSame($expectedUserAgent, $headers['User-Agent']);
        $this->assertSame($expectedUrl, (string) $baseUri);
    }

    public function testExceptionIsThrownIfApiKeyIsEmpty(): void
    {
        $this->expectException(ApiKeyException::class);
        $shopwareVersion = '6.7.4.0';

        $apiSettings = new ApiSettings('test_key', '', false);
        $fakeSettings = new FakeSettingsService(apiSettings: $apiSettings);
        $factory = new ClientFactory($fakeSettings, $shopwareVersion);

        $client = $factory->create(TestDefaults::SALES_CHANNEL);
    }
}
