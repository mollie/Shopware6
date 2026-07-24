<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PluginSettingsHandler extends AbstractHandler
{
    private const LOG_CHANNEL = 'mollie';
    private ?AbstractHandler $fileHandler = null;
    private ?bool $connectedCache = null;

    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        private Connection $connection,
        #[Autowire(value: '%kernel.logs_dir%/mollie_%kernel.environment%.log')]
        private string $filePath,
        #[Autowire(value: '%mollie.logger.level%')]
        private string $logLevel = LogLevel::INFO,
        bool $bubble = true
    ) {
        parent::__construct(LogLevel::DEBUG, $bubble);
    }

    public function handle(LogRecord $record): bool
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

        $logLevel = $loggerSettings->isDebugMode() ? LogLevel::DEBUG : $this->logLevel;
        $maxFiles = $loggerSettings->getLogFileDays();

        return new RotatingFileHandler($this->filePath, $maxFiles, $logLevel);
    }
}
