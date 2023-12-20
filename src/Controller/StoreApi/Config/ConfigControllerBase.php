<?php

namespace Kiener\MolliePayments\Controller\StoreApi\Config;

use Kiener\MolliePayments\Controller\StoreApi\Config\Response\ConfigResponse;
use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\SalesChannel\SalesChannelLocale;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Symfony\Component\Routing\Annotation\Route;

class ConfigControllerBase
{

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var SalesChannelLocale
     */
    private $salesChannelLocale;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SettingsService $settingsService
     * @param ConfigService $configService
     * @param SalesChannelLocale $salesChannelLocale
     * @param LoggerInterface $logger
     */
    public function __construct(SettingsService $settingsService, ConfigService $configService, SalesChannelLocale $salesChannelLocale, LoggerInterface $logger)
    {
        $this->settingsService = $settingsService;
        $this->configService = $configService;
        $this->salesChannelLocale = $salesChannelLocale;
        $this->logger = $logger;
    }


    /**
     * @Route("/store-api/mollie/config", name="store-api.mollie.config", methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @throws \Exception
     * @return StoreApiResponse
     */
    public function getConfig(SalesChannelContext $context): StoreApiResponse
    {
        try {
            $scId = $context->getSalesChannelId();

            $settings = $this->settingsService->getSettings($scId);

            $profileId = (string)$settings->getProfileId();
            $locale = $this->salesChannelLocale->getLocale($context);

            if (empty($profileId)) {
                # if its somehow not yet loaded (plugin config in admin when clicking save)
                # then load it right now
                $this->configService->fetchProfileId($scId);

                $settings = $this->settingsService->getSettings($scId);
                $profileId = (string)$settings->getProfileId();
            }

            return new ConfigResponse(
                $profileId,
                $settings->isTestMode(),
                $locale,
                $settings->isOneClickPaymentsEnabled()
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Error when fetching config in Store API: ' . $e->getMessage(),
                [
                    'error' => $e,
                ]
            );

            throw $e;
        }
    }
}
