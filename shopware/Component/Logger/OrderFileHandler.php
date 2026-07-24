<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Doctrine\DBAL\Connection;
use Mollie\Shopware\Component\Settings\AbstractSettingsService;
use Mollie\Shopware\Component\Settings\SettingsService;
use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OrderFileHandler extends AbstractSettingsLogHandler
{
    public function __construct(
        private OrderLogStorage $storage,
        #[Autowire(service: SettingsService::class)]
        AbstractSettingsService $settingsService,
        Connection $connection,
        #[Autowire(value: '%mollie.logger.level%')]
        string $logLevel = LogLevel::INFO,
        bool $bubble = false
    ) {
        parent::__construct($settingsService, $connection, $logLevel, $bubble);
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
            $handler = new StreamHandler($orderLogPath, $this->resolveLogLevel(), $this->bubble);

            // StreamHandler::handle() returns "false === bubble": with bubble=false the record is
            // consumed here (only the per-order file); with bubble=true it keeps propagating to the
            // following handlers as well, e.g. the main Mollie log.
            return $handler->handle($record);
        } catch (\Exception $e) {
            return false;
        }
    }
}
