<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Settings\Struct;

use Shopware\Core\Framework\Struct\Struct;

final class LoggerSettings extends Struct
{
    public const KEY_LOG_FILE_DAYS = 'logFileDays';
    public const KEY_DEBUG_MODE = 'debugMode';

    public function __construct(private bool $isDebugMode, private int $logFileDays)
    {
    }

    /**
     * @param array<string,mixed> $settings
     */
    public static function createFromShopwareArray(array $settings): self
    {
        $logFileDays = $settings[self::KEY_LOG_FILE_DAYS] ?? 0;
        $debugMode = $settings[self::KEY_DEBUG_MODE] ?? false;

        return new self((bool) $debugMode, (int) $logFileDays);
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
