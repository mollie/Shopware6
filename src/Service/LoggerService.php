<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Exception;
use Monolog\Logger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class LoggerService
{
    public const LOG_SOURCE = 'mollie_payments';
    public const LOG_ENTRY_CHANNEL = 'mollie_payments';

    public const LOG_ENTRY_KEY_ADDITIONAL_DATA = 'additionalData';
    public const LOG_ENTRY_KEY_CHANNEL = 'channel';
    public const LOG_ENTRY_KEY_CONTEXT = 'context';
    public const LOG_ENTRY_KEY_LEVEL = 'level';
    public const LOG_ENTRY_KEY_MESSAGE = 'message';
    public const LOG_ENTRY_KEY_SHOP_CONTEXT = 'shopContext';
    public const LOG_ENTRY_KEY_SOURCE = 'source';

    private $logEntryRepository;

    public function __construct(
        EntityRepositoryInterface $logEntryRepository
    )
    {
        $this->logEntryRepository = $logEntryRepository;
    }

    public function addEntry(
        $message,
        Context $context,
        ?Exception $exception = null,
        ?array $additionalData = null,
        int $level = Logger::DEBUG
    ): void
    {
        if (!is_array($additionalData)) {
            $additionalData = [];
        }

        // Add exception to array
        if ($exception !== null) {
            $additionalData['error'] = [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace(),
            ];
        }

        // Add data to the log entry
        $logEntry = [
            self::LOG_ENTRY_KEY_MESSAGE => mb_substr($message, 0, 255),
            self::LOG_ENTRY_KEY_LEVEL => $level,
            self::LOG_ENTRY_KEY_CHANNEL => mb_substr(self::LOG_ENTRY_CHANNEL, 0, 255),
            self::LOG_ENTRY_KEY_CONTEXT => [
                self::LOG_ENTRY_KEY_SOURCE => self::LOG_SOURCE,
                self::LOG_ENTRY_KEY_ADDITIONAL_DATA => $additionalData,
                self::LOG_ENTRY_KEY_SHOP_CONTEXT => $context !== null ? $context->getVars() : null,
            ],
        ];

        // Insert the log entry in the database
        $this->logEntryRepository->create([$logEntry], $context);
    }
}