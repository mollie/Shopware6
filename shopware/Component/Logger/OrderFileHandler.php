<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Monolog\Handler\AbstractHandler;
use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OrderFileHandler extends AbstractHandler
{
    public function __construct(
        #[Autowire(service: SettingsService::class)]
        private AbstractSettingsService $settingsService,
        private OrderLogStorage $storage,
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

        $loggerSettings = $this->settingsService->getLoggerSettings();
        $logLevel = $loggerSettings->isDebugMode() ? LogLevel::DEBUG : LogLevel::WARNING;

        try {
            $handler = new StreamHandler($orderLogPath, $logLevel, $this->bubble);

            return $handler->handle($record);
        } catch (\Exception) {
            return false;
        }
    }
}
