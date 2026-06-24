<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Logger;

use Monolog\Handler\AbstractHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Log\LogLevel;

final class OrderFileHandler extends AbstractHandler
{
    public function __construct(
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

        try {
            $handler = new StreamHandler($orderLogPath, LogLevel::DEBUG);
            $handler->handle($record);
        } catch (\Exception $e) {
            return false;
        }

        return $record->level->value < Level::Warning->value;
    }
}
