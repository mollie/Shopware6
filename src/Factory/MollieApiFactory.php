<?php

namespace Kiener\MolliePayments\Factory;

use Exception;
use Kiener\MolliePayments\MolliePayments;
use Kiener\MolliePayments\Service\MollieApi\Client\MollieHttpClient;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Kernel;

class MollieApiFactory
{
    /**
     * @var string
     */
    private $shopwareVersion;

    /**
     * @var MollieApiClient
     */
    private $apiClient;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param string $shopwareVersion
     * @param SettingsService $settingsService
     * @param LoggerInterface $logger
     */
    public function __construct(string $shopwareVersion, SettingsService $settingsService, LoggerInterface $logger)
    {
        $this->shopwareVersion = $shopwareVersion;
        $this->settingsService = $settingsService;
        $this->logger = $logger;

        if (empty($this->shopwareVersion)) {
            $this->shopwareVersion = Kernel::SHOPWARE_FALLBACK_VERSION;
        }
    }

    /**
     * Create a new instance of the Mollie API client.
     * @param null|string $salesChannelId
     *
     * @return MollieApiClient
     * @deprecated please use the getClient option in the future
     */
    public function createClient(?string $salesChannelId = null): MollieApiClient
    {
        # the singleton approach here was too risky,
        # everyone who used this was never able to switch api keys through sales channels.
        # now it's the same as getClient() -> should be combined one day
        return $this->getClient($salesChannelId);
    }

    /**
     * Returns a new instance of the Mollie API client.
     *
     * @param null|string $salesChannelId
     *
     * @return MollieApiClient
     */
    public function getClient(?string $salesChannelId = null): MollieApiClient
    {
        $settings = $this->settingsService->getSettings($salesChannelId);

        if ($settings->isTestMode()) {
            return $this->getTestClient((string)$salesChannelId);
        }

        return $this->getLiveClient((string)$salesChannelId);
    }

    /**
     * @param string $salesChannelId
     * @return MollieApiClient
     */
    public function getLiveClient(string $salesChannelId): MollieApiClient
    {
        if (empty($salesChannelId)) {
            $settings = $this->settingsService->getSettings(null);
        } else {
            $settings = $this->settingsService->getSettings($salesChannelId);
        }

        $apiKey = $settings->getLiveApiKey();

        return $this->buildClient($apiKey);
    }

    /**
     * @param string $salesChannelId
     * @return MollieApiClient
     */
    public function getTestClient(string $salesChannelId): MollieApiClient
    {
        if (empty($salesChannelId)) {
            $settings = $this->settingsService->getSettings(null);
        } else {
            $settings = $this->settingsService->getSettings($salesChannelId);
        }

        $apiKey = $settings->getTestApiKey();

        # now check if our TEST api key starts with "live_"...if that is the case
        # do NOT use it, and log an error. This helps us to avoid that merchants
        # accidentally use a PROD key in test mode.
        if ($this->stringContains('live_', $apiKey)) {
            $apiKey = '';

            $this->logger->emergency('Attention, you are using a live Api key for test mode! This is not possible! Please verify the key you are using for testing in the plugin configuration!');
        }

        return $this->buildClient($apiKey);
    }

    /**
     * @param string $apiKey
     * @return MollieApiClient
     */
    public function buildClient(string $apiKey): MollieApiClient
    {
        try {
            # in some rare peaks, the Mollie API might take a bit more time.
            # so we set it a higher connect timeout, and also a high enough response timeout
            $connectTimeout = 5;
            $responseTimeout = 10;
            $httpClient = new MollieHttpClient($connectTimeout, $responseTimeout);

            $this->apiClient = new MollieApiClient($httpClient);
            $this->apiClient->setApiKey($apiKey);

            $this->apiClient->addVersionString('Shopware/' . $this->shopwareVersion);

            $this->apiClient->addVersionString('MollieShopware6/' . MolliePayments::PLUGIN_VERSION);
        } catch (Exception $e) {
            # the Invalid API if not starting with test_ or live_ (and more) is coming through an exception.
            # but we don't want this to happen in here...it's just annoying...so only log other errors
            if ($this->stringContains('Invalid API key', $e->getMessage()) === false) {
                $this->logger->error($e->getMessage(), [$e]);
            }
        }

        return $this->apiClient;
    }

    /**
     * @param string $search
     * @param string $text
     * @return bool
     */
    private function stringContains(string $search, string $text): bool
    {
        return (strpos($text, $search) !== false);
    }
}
