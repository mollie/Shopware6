<?php

namespace Kiener\MolliePayments\Factory;

use Exception;
use Kiener\MolliePayments\Config\Config;
use Mollie\Api\MollieApiClient;
use Psr\Log\LoggerInterface;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MollieApiFactory
{
    /** @var ContainerInterface $container */
    protected $container;

    /** @var Config $config */
    protected $config;

    /** @var LoggerInterface */
    protected $logger;

    /** @var MollieApiClient $apiClient */
    protected $apiClient;

    /**
     * Create a new instance of MollieApiFactory.
     *
     * @param ContainerInterface $container
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        ContainerInterface $container,
        Config $config,
        LoggerInterface $logger
    )
    {
        $this->container = $container;
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Create a new instance of the Mollie API client.
     *
     * @throws \Mollie\Api\Exceptions\IncompatiblePlatform
     */
    public function createClient()
    {
        if ($this->apiClient === null) {
            $this->apiClient = new MollieApiClient();

            try {
                // Set the API key
                $this->apiClient->setApiKey(
                    $this->config::testMode() ? $this->config::testApiKey() : $this->config::liveApiKey()
                );

                // Add platform data
                $this->apiClient->addVersionString(
                    'Shopware/' .
                    Kernel::SHOPWARE_FALLBACK_VERSION
                );

                // @todo Add plugin version variable
                $this->apiClient->addVersionString(
                    'MollieShopware6/1.0.2'
                );
            } catch (Exception $e) {
                $this->logger->error($e->getMessage(), [$e]);
            }
        }

        return $this->apiClient;
    }
}