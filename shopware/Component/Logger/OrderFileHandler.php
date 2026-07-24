<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OrderFileHandler extends AbstractHandler
{
    private ?bool $connectedCache = null;
    private ?string $resolvedLogLevel = null;

    public function __construct(
        private OrderLogStorage $storage,
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        private Connection $connection,
        #[Autowire(value: '%mollie.logger.level%')]
        private string $logLevel = LogLevel::INFO,
        bool $bubble = false
    ) {
        parent::__construct(LogLevel::DEBUG, $bubble);
    }

    public function handle(LogRecord $record): bool
    {
        $orderNumber = $record->context['orderNumber'] ?? null;

        if ($orderNumber === null) {
            return false;
        }

        $orderNumber = (string) $orderNumber;

        if (strlen($orderNumber) === 0) {
            return false;
        }

        $orderLogPath = $this->storage->resolveLogFile($orderNumber);

        try {
            $handler = new StreamHandler($orderLogPath, $this->resolveLogLevel());
            $handler->handle($record);
        } catch (\Exception $e) {
            return false;
        }

        return $record->level->value < Level::Warning->value;
    }

    private function resolveLogLevel(): string
    {
        if ($this->resolvedLogLevel !== null) {
            return $this->resolvedLogLevel;
        }

        if ($this->isConnected() === false) {
            return $this->logLevel;
        }

        $this->resolvedLogLevel = $this->settingsService->getLoggerSettings()->isDebugMode() ? LogLevel::DEBUG : $this->logLevel;

        return $this->resolvedLogLevel;
    }

    private function isConnected(): bool
    {
        if ($this->connectedCache !== null) {
            return $this->connectedCache;
        }

        $this->connectedCache = $this->connection->isConnected();

        return $this->connectedCache;
    }
}
