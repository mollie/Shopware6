<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Client;
use Kiener\MolliePayments\MolliePayments;
use Mollie\Shopware\Component\Mollie\Exception\ApiKeyException;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ClientFactory implements ClientFactoryInterface
{
    private const MOLLIE_BASE_URL = 'https://api.mollie.com/v2/';

    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settings,
        #[Autowire(value: '%kernel.shopware_version%')]
        private string $shopwareVersion,
    ) {
    }

    public function create(?string $salesChannelId = null, bool $forceLive = false): Client
    {
        $apiSettings = $this->settings->getApiSettings($salesChannelId);

        $apiKey = $apiSettings->getApiKey();
        $testMode = $apiSettings->isTestMode();
        if ($forceLive) {
            $apiKey = $apiSettings->getLiveApiKey();
            $testMode = false;
        }
        if (mb_strlen($apiKey) === 0) {
            $message = sprintf('API key is empty. SalesChannelId: %s, TestMode: %s', $salesChannelId, $testMode ? 'true' : 'false');
            throw new ApiKeyException($message);
        }

        return $this->buildClient($apiKey);
    }

    public function createForKey(string $apiKey): Client
    {
        return $this->buildClient($apiKey);
    }

    private function buildClient(string $apiKey): Client
    {
        return new Client([
            'base_uri' => self::MOLLIE_BASE_URL,
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $apiKey),
                'User-Agent' => implode(' ', [
                    'Shopware/' . $this->shopwareVersion,
                    'MollieShopware6/' . MolliePayments::PLUGIN_VERSION,
                ]),
            ],
        ]);
    }
}
