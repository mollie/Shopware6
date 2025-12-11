<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\StreamHandler;
use Monolog\LogRecord;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OrderFileHandler extends AbstractHandler
{
    private string $logDir;

    public function __construct(
        #[Autowire(value: '%kernel.logs_dir%')]
        string $logDir,
        bool $bubble = false
    ) {
        parent::__construct(LogLevel::DEBUG, $bubble);
        $this->logDir = $logDir;
    }

    public function handle(LogRecord $record): bool
    {
        $orderNumber = $record->context['orderNumber'] ?? null;

        if ($orderNumber === null) {
            return false;
        }

        $orderNumber = (string) $orderNumber;

        $mollieLogDir = $this->logDir . '/mollie';
        if (! is_dir($mollieLogDir)) {
            @mkdir($mollieLogDir, 0755, true);
        }

        $orderLogPath = $mollieLogDir . '/order-' . $orderNumber . '.log';

        try {
            $handler = new StreamHandler($orderLogPath, LogLevel::DEBUG);
            $handler->handle($record);
        } catch (\Exception $e) {
            return false;
        }

        // don't pass to other handlers
        return ! $this->bubble;
    }
}
