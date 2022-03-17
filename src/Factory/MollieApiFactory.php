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
     * @param string|null $salesChannelId
     *
     * @return MollieApiClient
     * @throws IncompatiblePlatform
     * @deprecated please use the getClient option in the future
     */
    public function createClient(?string $salesChannelId = null): MollieApiClient
    {
        # the singleton approach here was too risky,
        # everyone who used this was never able to switch api keys through sales channels.
        # now its the same as getClient() -> should be combined one day
        return $this->getClient($salesChannelId);
    }

    /**
     * Returns a new instance of the Mollie API client.
     *
     * @param string|null $salesChannelId
     *
     * @return MollieApiClient
     */
    public function getClient(?string $salesChannelId = null): MollieApiClient
    {
        $settings = $this->settingsService->getSettings($salesChannelId);
        $apiKey = ($settings->isTestMode()) ? $settings->getTestApiKey() : $settings->getLiveApiKey();

        return $this->buildClient($apiKey);
    }

    /**
     * @param string $salesChannelId
     * @return MollieApiClient
     */
    public function getLiveClient(string $salesChannelId): MollieApiClient
    {
        $settings = $this->settingsService->getSettings($salesChannelId);
        $apiKey = $settings->getLiveApiKey();

        return $this->buildClient($apiKey);
    }

    /**
     * @param string $salesChannelId
     * @return MollieApiClient
     */
    public function getTestClient(string $salesChannelId): MollieApiClient
    {
        $settings = $this->settingsService->getSettings($salesChannelId);
        $apiKey = $settings->getTestApiKey();

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
            $this->logger->error($e->getMessage(), [$e]);
        }

        # TODO we should change to fail-fast one day, but not at this time!
        return $this->apiClient;
    }


}
