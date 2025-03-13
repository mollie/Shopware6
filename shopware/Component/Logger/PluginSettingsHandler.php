<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Log\LogLevel;

final class PluginSettingsHandler extends AbstractHandler
{
    private const LOG_CHANNEL = 'mollie';
    private ?AbstractHandler $fileHandler = null;
    private Connection $connection;
    private string $filePath;
    private ?bool $connectedCache = null;

    private AbstractSettingsService $settingsService;

    /**
     * @param int|Level|string $level
     */
    public function __construct(AbstractSettingsService $settingsService, Connection $connection, string $filePath, bool $bubble = true)
    {
        parent::__construct(LogLevel::DEBUG, $bubble);
        $this->connection = $connection;
        $this->filePath = $filePath;
        $this->settingsService = $settingsService;
    }

    /**
     * We need to define types here because shopware 6.4 uses old monologger where LogRecord does not exists.
     *
     * @param array|LogRecord $record
     */
    public function handle($record): bool
    {
        if ($this->isConnected() === false) {
            return false;
        }

        $channel = $record['channel'] ?? null;
        if ($channel !== self::LOG_CHANNEL) {
            return false;
        }

        if ($this->fileHandler === null) {
            $this->fileHandler = $this->initializeHandler();
        }

        $this->fileHandler->handle($record);

        return $this->bubble === false;
    }

    private function isConnected(): bool
    {
        if ($this->connectedCache !== null) {
            return $this->connectedCache;
        }

        $this->connectedCache = $this->connection->isConnected();

        return $this->connectedCache;
    }

    private function initializeHandler(): AbstractHandler
    {
        $loggerSettings = $this->settingsService->getLoggerSettings();

        $logLevel = $loggerSettings->isDebugMode() ? LogLevel::DEBUG : LogLevel::INFO;
        $maxFiles = $loggerSettings->getLogFileDays();

        return new RotatingFileHandler($this->filePath, $maxFiles, $logLevel);
    }
}
