<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Repository\SalesChannel\SalesChannelRepositoryInterface;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Framework\Context;
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
     * @var SalesChannelRepositoryInterface
     */
    private $repoSalesChannels;

    /**
     * @var string
     */
    private $envShopDomain;

    /**
     * @var string
     */
    private $envDevMode;

    /**
     * @var string
     */
    private $envCypressMode;


    /**
     * @param SystemConfigService $systemConfigService
     * @param SalesChannelRepositoryInterface $repoSalesChannels
     * @param ?string $envShopDomain
     * @param ?string $envDevMode
     * @param ?string $envCypressMode
     */
    public function __construct(SystemConfigService $systemConfigService, SalesChannelRepositoryInterface $repoSalesChannels, ?string $envShopDomain, ?string $envDevMode, ?string $envCypressMode)
    {
        $this->systemConfigService = $systemConfigService;
        $this->repoSalesChannels = $repoSalesChannels;

        $this->envShopDomain = (string)$envShopDomain;
        $this->envDevMode = (string)$envDevMode;
        $this->envCypressMode = (string)$envCypressMode;
    }

    /**
     * Get Mollie settings from configuration.
     *
     * @param null|string $salesChannelId
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

        /** @var string[] $resultIDs */
        $resultIDs = $this->repoSalesChannels->searchIds(new Criteria(), $context)->getIds();

        foreach ($resultIDs as $scID) {
            $allConfigs[(string)$scID] = $this->getSettings((string)$scID);
        }

        return $allConfigs;
    }


    /**
     * @param string $key
     * @param mixed $value
     * @param null|string $salesChannelId
     */
    public function set(string $key, $value, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->set(self::SYSTEM_CONFIG_DOMAIN . $key, $value, $salesChannelId);
    }

    /**
     * @param string $key
     * @param null|string $salesChannelId
     */
    public function delete(string $key, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->delete(self::SYSTEM_CONFIG_DOMAIN . $key, $salesChannelId);
    }

    /**
     * @param null|string $profileId
     * @param null|string $salesChannelId
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
        return trim($this->envShopDomain);
    }

    /**
     * This turns on the DEV mode in the plugin.
     * We try to always implement things as close to production as possible,
     * but sometimes we need a DEV mode ;).
     * @return bool
     */
    public function getEnvMollieDevMode(): bool
    {
        $devMode = trim($this->envDevMode);
        return ($devMode === '1');
    }

    /**
     * @return bool
     */
    public function getMollieCypressMode(): bool
    {
        $devMode = trim($this->envCypressMode);
        return ($devMode === '1');
    }
}
