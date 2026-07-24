<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Monolog\Handler\AbstractHandler;
use Monolog\Level;
use Psr\Log\LogLevel;

abstract class AbstractSettingsLogHandler extends AbstractHandler
{
    private ?bool $connectedCache = null;
    private ?Level $resolvedLogLevel = null;

    public function __construct(
        protected AbstractSettingsService $settingsService,
        private Connection $connection,
        private string $logLevel,
        bool $bubble
    ) {
        parent::__construct(LogLevel::DEBUG, $bubble);
    }

    protected function isConnected(): bool
    {
        if ($this->connectedCache !== null) {
            return $this->connectedCache;
        }

        $this->connectedCache = $this->connection->isConnected();

        return $this->connectedCache;
    }

    /**
     * Resolves the minimum log level the handler should apply. Debug mode always
     * wins and forces the debug level; otherwise the configured level is used.
     * Falls back to the configured level while the database is unavailable.
     */
    protected function resolveLogLevel(): Level
    {
        if ($this->resolvedLogLevel !== null) {
            return $this->resolvedLogLevel;
        }

        if ($this->isConnected() === false) {
            return $this->toLevel($this->logLevel);
        }

        $logLevel = $this->settingsService->getLoggerSettings()->isDebugMode() ? LogLevel::DEBUG : $this->logLevel;
        $this->resolvedLogLevel = $this->toLevel($logLevel);

        return $this->resolvedLogLevel;
    }

    private function toLevel(string $level): Level
    {
        return match (strtolower($level)) {
            LogLevel::DEBUG => Level::Debug,
            LogLevel::INFO => Level::Info,
            LogLevel::NOTICE => Level::Notice,
            LogLevel::WARNING => Level::Warning,
            LogLevel::ERROR => Level::Error,
            LogLevel::CRITICAL => Level::Critical,
            LogLevel::ALERT => Level::Alert,
            LogLevel::EMERGENCY => Level::Emergency,
            default => Level::Info,
        };
    }
}
