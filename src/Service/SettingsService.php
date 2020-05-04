<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SettingsService
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config.';

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
     * @param Context|null $context
     *
     * @return MollieSettingStruct
     */
    public function getSettings(?string $salesChannelId = null, ?Context $context = null): MollieSettingStruct
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
}