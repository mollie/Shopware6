<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Client;
use Kiener\MolliePayments\MolliePayments;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;

final class ClientFactory implements ClientFactoryInterface
{
    private const MOLLIE_BASE_URL = 'https://api.mollie.com/v2/';

    public function __construct(
        private AbstractSettingsService $settings,
        private string $shopwareVersion,
    ) {
    }

    public function create(string $salesChannelId): Client
    {
        $apiSettings = $this->settings->getApiSettings($salesChannelId);

        $userAgent = implode(' ', [
            'Shopware/' . $this->shopwareVersion,
            'MollieShopware6/' . MolliePayments::PLUGIN_VERSION,
        ]);
        if (mb_strlen($apiSettings->getApiKey()) === 0) {
            $message = sprintf('API key is empty. SalesChannelId: %s, TestMode: %s', $salesChannelId, $apiSettings->isTestMode() ? 'true' : 'false');
            throw new ApiKeyException($message);
        }

        return new Client([
            'base_uri' => self::MOLLIE_BASE_URL,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiSettings->getApiKey(),
                'User-Agent' => $userAgent
            ]
        ]);
    }
}
