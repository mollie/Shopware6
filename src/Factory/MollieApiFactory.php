<?php

namespace Kiener\MolliePayments\Factory;

use Exception;
use Kiener\MolliePayments\MolliePayments;
use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Kernel;

class MollieApiFactory
{

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
     * @param SettingsService $settingsService
     * @param LoggerInterface $logger
     */
    public function __construct(SettingsService $settingsService, LoggerInterface $logger)
    {
        $this->settingsService = $settingsService;
        $this->logger = $logger;
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
     * @param string $salesChannelId
     * @return MollieApiClient
     */
    public function getLiveClient(string $salesChannelId): MollieApiClient
    {
        return $this->buildClient($salesChannelId, false);
    }

    /**
     * @param string $salesChannelId
     * @return MollieApiClient
     */
    public function getTextClient(string $salesChannelId): MollieApiClient
    {
        return $this->buildClient($salesChannelId, true);
    }

    /**
     * Returns a new instance of the Mollie API client.
     *
     * @param string|null $salesChannelId
     *
     * @return MollieApiClient
     * @throws IncompatiblePlatform
     */
    public function getClient(?string $salesChannelId = null): MollieApiClient
    {
        $this->apiClient = new MollieApiClient();

        $settings = $this->settingsService->getSettings($salesChannelId);

        try {
            // Set the API key
            $this->apiClient->setApiKey(
                $settings->isTestMode() ? $settings->getTestApiKey() : $settings->getLiveApiKey()
            );

            // Add platform data
            $this->apiClient->addVersionString(
                'Shopware/' .
                Kernel::SHOPWARE_FALLBACK_VERSION
            );

            // @todo Add plugin version variable
            $this->apiClient->addVersionString(
                'MollieShopware6/1.5.3'
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        return $this->apiClient;
    }


    /**
     * @param string $salesChannelId
     * @param bool $testMode
     * @return MollieApiClient
     */
    private function buildClient(string $salesChannelId, bool $testMode): MollieApiClient
    {
        $this->apiClient = new MollieApiClient();

        $settings = $this->settingsService->getSettings($salesChannelId);

        try {

            $apiKey = ($testMode) ? $settings->getTestApiKey() : $settings->getLiveApiKey();

            $this->apiClient->setApiKey($apiKey);

            // Add platform data
            $this->apiClient->addVersionString('Shopware/' . Kernel::SHOPWARE_FALLBACK_VERSION);

            // @todo Add plugin version variable
            $this->apiClient->addVersionString('MollieShopware6/1.5.3');

        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        return $this->apiClient;
    }

}
