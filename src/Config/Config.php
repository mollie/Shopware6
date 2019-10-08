<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Config;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class Config
{
    public const SYSTEM_CONFIG_DOMAIN = 'MolliePayments.config.';

    /** @var SystemConfigService $systemConfigService */
    protected static $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService)
    {
        self::$systemConfigService = $systemConfigService;
    }

    public static function liveApiKey() : string
    {
        return (string) self::$systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . 'liveApiKey');
    }

    public static function testApiKey() : string
    {
        return (string) self::$systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . 'testApiKey');
    }

    public static function testMode() : bool
    {
        return (bool) self::$systemConfigService->get(self::SYSTEM_CONFIG_DOMAIN . 'testMode');
    }
}