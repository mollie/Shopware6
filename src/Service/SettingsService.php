<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SettingsService implements PluginSettingsServiceInterface
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config.';

    const LIVE_API_KEY = 'liveApiKey';
    const TEST_API_KEY = 'testApiKey';
    const LIVE_PROFILE_ID = 'liveProfileId';
    const TEST_PROFILE_ID = 'testProfileId';


    /**
     * @var SystemConfigService
     */
    protected $systemConfigService;

    /**
     *
     * @var EntityRepositoryInterface
     */
    private $repoSalesChannels;


    /**
     * @param SystemConfigService $systemConfigService
     * @param EntityRepositoryInterface $repoSalesChannels
     */
    public function __construct(SystemConfigService $systemConfigService, EntityRepositoryInterface $repoSalesChannels)
    {
        $this->systemConfigService = $systemConfigService;
        $this->repoSalesChannels = $repoSalesChannels;
    }

    /**
     * Get Mollie settings from configuration.
     *
     * @param string|null $salesChannelId
     *
     * @return MollieSettingStruct
     */
    public function getSettings(?string $salesChannelId = null): MollieSettingStruct
    {
        $structData = [];
        $systemConfigData = $this->systemConfigService->getDomain(self::SYSTEM_CONFIG_DOMAIN, $salesChannelId, true);

        foreach ($systemConfigData as $key => $value) {
            if (stripos($key, self::SYSTEM_CONFIG_DOMAIN) !== false) {
                $structData[substr($key, strlen(self::SYSTEM_CONFIG_DOMAIN))] = $value;
            } else {
                $structData[$key] = $value;
            }
        }

        return (new MollieSettingStruct())->assign($structData);
    }

    /**
     * Gets all configurations of all sales channels.
     * Every sales channel will be a separate entry in the array.
     *
     * @param Context $context
     * @return array<string, MollieSettingStruct>
     */
    public function getAllSalesChannelSettings(Context $context): array
    {
        $allConfigs = [];

        $result = $this->repoSalesChannels->searchIds(new Criteria(), $context);

        foreach ($result->getIds() as $scID) {
            $allConfigs[$scID] = $this->getSettings($scID);
        }

        return $allConfigs;
    }


    /**
     * @param string $key
     * @param $value
     * @param string|null $salesChannelId
     */
    public function set(string $key, $value, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->set(self::SYSTEM_CONFIG_DOMAIN . $key, $value, $salesChannelId);
    }

    /**
     * @param string $key
     * @param string|null $salesChannelId
     */
    public function delete(string $key, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->delete(self::SYSTEM_CONFIG_DOMAIN . $key, $salesChannelId);
    }

    /**
     * @param string|null $profileId
     * @param string|null $salesChannelId
     * @param bool $testMode
     */
    public function setProfileId(?string $profileId, ?string $salesChannelId = null, bool $testMode = false): void
    {
        $key = $testMode ? self::TEST_PROFILE_ID : self::LIVE_PROFILE_ID;

        if (!is_null($profileId)) {
            $this->set($key, $profileId, $salesChannelId);
        } else {
            $this->delete($key, $salesChannelId);
        }
    }

    /**
     * Gets the custom shop domain from the .env file.
     * This can be used for local NGROK approaches, or also
     * if you want to use a dedicated domain as the webhook endpoint
     * for your Mollie payments.
     * @return string
     */
    public function getEnvMollieShopDomain(): string
    {
        return trim((string)getenv('MOLLIE_SHOP_DOMAIN'));
    }

    /**
     * This turns on the DEV mode in the plugin.
     * We try to always implement things as close to production as possible,
     * but sometimes we need a DEV mode ;).
     * @return bool
     */
    public function getEnvMollieDevMode(): bool
    {
        $devMode = trim((string)getenv('MOLLIE_DEV_MODE'));
        return ($devMode === '1');
    }

}
