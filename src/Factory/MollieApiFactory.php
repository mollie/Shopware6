<?php

namespace Kiener\MolliePayments\Factory;

use Exception;
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
    /** @var MollieApiClient */
    private $apiClient;

    /** @var SettingsService */
    private $settingsService;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Create a new instance of MollieApiFactory.
     *
     * @param SettingsService $settingsService
     * @param LoggerInterface $logger
     */
    public function __construct(
        SettingsService $settingsService,
        LoggerInterface $logger
    )
    {
        $this->settingsService = $settingsService;
        $this->logger = $logger;
    }

    /**
     * Create a new instance of the Mollie API client.
     *
     * @param string|null $salesChannelId
     *
     * @return MollieApiClient
     * @throws IncompatiblePlatform
     */
    public function createClient(?string $salesChannelId = null): MollieApiClient
    {
        # TODO Refactor into getClient() below
        # the singleton approach here was too risky,
        # everyone who used this was never able to switch api keys through sales channels.
        # now its the same as getClient() -> should be combined one day
        return $this->getClient($salesChannelId);
    }

    /**
     * Returns a new instance of the Mollie API client.
     *
     * @param string|null $salesChannelId
     * @param Context|null $context
     *
     * @return MollieApiClient
     * @throws IncompatiblePlatform
     */
    public function getClient(?string $salesChannelId = null, ?Context $context = null): MollieApiClient
    {
        $this->apiClient = new MollieApiClient();

        $settings = $this->settingsService->getSettings($salesChannelId, $context);

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
                'MollieShopware6/1.4.1'
            );
        } catch (Exception $e) {
            $this->logger->error($e->getMessage(), [$e]);
        }

        return $this->apiClient;
    }
}
