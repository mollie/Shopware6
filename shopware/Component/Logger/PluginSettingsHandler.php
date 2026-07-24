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

final class PluginSettingsHandler extends AbstractSettingsLogHandler
{
    private const LOG_CHANNEL = 'mollie';
    private ?AbstractHandler $fileHandler = null;

    public function __construct(
        #[Autowire(service: SettingsService::class)]
        AbstractSettingsService $settingsService,
        Connection $connection,
        #[Autowire(value: '%kernel.logs_dir%/mollie_%kernel.environment%.log')]
        private string $filePath,
        #[Autowire(value: '%mollie.logger.level%')]
        string $logLevel = LogLevel::INFO,
        bool $bubble = true
    ) {
        parent::__construct($settingsService, $connection, $logLevel, $bubble);
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

    private function initializeHandler(): AbstractHandler
    {
        $maxFiles = $this->settingsService->getLoggerSettings()->getLogFileDays();
        $logLevel = $this->resolveLogLevel();

        return new RotatingFileHandler($this->filePath, $maxFiles, $logLevel);
    }
}
