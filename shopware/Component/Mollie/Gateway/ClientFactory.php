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

        return new Client([
            'base_uri' => self::MOLLIE_BASE_URL,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiSettings->getApiKey(),
                'User-Agent' => $userAgent
            ]
        ]);
    }
}
