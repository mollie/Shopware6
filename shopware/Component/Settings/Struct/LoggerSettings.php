<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\JsonSerializableTrait;
use Shopware\Core\Framework\Struct\Struct;

final class LoggerSettings extends Struct
{
    use JsonSerializableTrait;
    public const KEY_LOG_FILE_DAYS = 'logFileDays';
    public const KEY_LOG_SUCCESS_DAYS = 'logSuccessDays';
    public const KEY_LOG_FAILED_DAYS = 'logFailedDays';
    public const KEY_DEBUG_MODE = 'debugMode';

    public const DEFAULT_LOG_SUCCESS_DAYS = 7;
    public const DEFAULT_LOG_FAILED_DAYS = 30;

    public function __construct(
        private bool $isDebugMode,
        private int $logFileDays,
        private int $logSuccessDays = self::DEFAULT_LOG_SUCCESS_DAYS,
        private int $logFailedDays = self::DEFAULT_LOG_FAILED_DAYS
    ) {
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $logFileDays = $settings[self::KEY_LOG_FILE_DAYS] ?? 0;
        $logSuccessDays = $settings[self::KEY_LOG_SUCCESS_DAYS] ?? self::DEFAULT_LOG_SUCCESS_DAYS;
        $logFailedDays = $settings[self::KEY_LOG_FAILED_DAYS] ?? self::DEFAULT_LOG_FAILED_DAYS;
        $debugMode = $settings[self::KEY_DEBUG_MODE] ?? false;

        return new self((bool) $debugMode, (int) $logFileDays, (int) $logSuccessDays, (int) $logFailedDays);
    }

    public function isDebugMode(): bool
    {
        return $this->isDebugMode === true;
    }

    public function getLogFileDays(): int
    {
        return $this->logFileDays;
    }

    public function getLogSuccessDays(): int
    {
        return $this->logSuccessDays;
    }

    public function getLogFailedDays(): int
    {
        return $this->logFailedDays;
    }
}
