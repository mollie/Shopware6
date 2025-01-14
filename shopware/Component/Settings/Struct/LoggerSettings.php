<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class LoggerSettings extends Struct
{
    public const KEY_LOG_FILE_DAYS = 'logFileDays';
    public const KEY_DEBUG_MODE = 'debugMode';
    private bool $isDebugMode = false;
    private int $logFileDays = 0;


    /**
     * @param bool $isDebugMode
     * @param int $logFileDays
     */
    public function __construct(bool $isDebugMode, int $logFileDays)
    {
        $this->isDebugMode = $isDebugMode;
        $this->logFileDays = $logFileDays;
    }

    public static function createFromShopwareArray(array $settings): LoggerSettings
    {
        $logFileDays = $settings[self::KEY_LOG_FILE_DAYS] ?? 0;
        $debugMode = $settings[self::KEY_DEBUG_MODE] ?? false;

        return new LoggerSettings($debugMode, $logFileDays);
    }

    public function isDebugMode(): bool
    {
        return $this->isDebugMode === true;
    }

    public function getLogFileDays(): int
    {
        return $this->logFileDays;
    }
}
