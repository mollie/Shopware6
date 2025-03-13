<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Controller\StoreApi\Config;

use Kiener\MolliePayments\Controller\StoreApi\Config\Response\ConfigResponse;
use Kiener\MolliePayments\Service\ConfigService;
use Kiener\MolliePayments\Service\MollieLocaleService;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

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
     * @var MollieLocaleService
     */
    private $mollieLocaleService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(SettingsService $settingsService, ConfigService $configService, MollieLocaleService $mollieLocaleService, LoggerInterface $logger)
    {
        $this->settingsService = $settingsService;
        $this->configService = $configService;
        $this->mollieLocaleService = $mollieLocaleService;
        $this->logger = $logger;
    }

    /**
     * @throws \Exception
     */
    public function getConfig(SalesChannelContext $context): StoreApiResponse
    {
        try {
            $scId = $context->getSalesChannelId();

            $settings = $this->settingsService->getSettings($scId);

            $profileId = (string) $settings->getProfileId();
            $locale = $this->mollieLocaleService->getLocale($context);

            if (empty($profileId)) {
                // if its somehow not yet loaded (plugin config in admin when clicking save)
                // then load it right now
                $this->configService->fetchProfileId($scId);

                $settings = $this->settingsService->getSettings($scId);
                $profileId = (string) $settings->getProfileId();
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
