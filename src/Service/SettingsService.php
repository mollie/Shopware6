<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SettingsService
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config.';

    const LIVE_API_KEY = 'liveApiKey';
    const TEST_API_KEY = 'testApiKey';
    const LIVE_PROFILE_ID = 'liveProfileId';
    const TEST_PROFILE_ID = 'testProfileId';

    /** @var SystemConfigService */
    protected $systemConfigService;

    public function __construct(
        SystemConfigService $systemConfigService
    )
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * Get Mollie settings from configuration.
     *
     * @param string|null  $salesChannelId
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

    public function set(string $key, $value, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->set(self::SYSTEM_CONFIG_DOMAIN . $key, $value, $salesChannelId);
    }

    public function delete(string $key, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->delete(self::SYSTEM_CONFIG_DOMAIN . $key, $salesChannelId);
    }

    public function setProfileId(?string $profileId, ?string $salesChannelId = null, bool $testMode = false): void
    {
        $key = $testMode ? self::TEST_PROFILE_ID : self::LIVE_PROFILE_ID;

        if(!is_null($profileId)) {
            $this->set($key, $profileId, $salesChannelId);
        } else {
            $this->delete($key, $salesChannelId);
        }
    }
}
