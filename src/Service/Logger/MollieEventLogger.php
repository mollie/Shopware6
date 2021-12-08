<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Logger;

use Exception;
use Monolog\Logger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;


class MollieEventLogger
{

    /**
     * @var EntityRepositoryInterface
     */
    private $logEntryRepository;


    /**
     * @param EntityRepositoryInterface $logEntryRepository
     */
    public function __construct(EntityRepositoryInterface $logEntryRepository)
    {
        $this->logEntryRepository = $logEntryRepository;
    }

    /**
     * @param string $message
     * @param array $additionalData
     * @param Context $context
     */
    public function info(string $message, array $additionalData, Context $context): void
    {
        $this->saveEntry($message, Logger::INFO, $additionalData, $context);
    }

    /**
     * @param string $message
     * @param array $additionalData
     * @param \Throwable $exception
     * @param Context $context
     */
    public function error(string $message, array $additionalData, \Throwable $exception, Context $context): void
    {
        $additionalData['error'] = [
            'message' => $exception->getMessage(),
            'trace' => $exception->getTrace(),
        ];

        $this->saveEntry($message, Logger::ERROR, $additionalData, $context);
    }

    /**
     * @param string $message
     * @param int $logLevel
     * @param array $additionalData
     * @param Context $context
     */
    private function saveEntry(string $message, int $logLevel, array $additionalData, Context $context): void
    {
        $logEntry = [
            'message' => mb_substr($message, 0, 255),
            'level' => $logLevel,
            'channel' => mb_substr('mollie_payments', 0, 255),
            'context' => [
                'source' => 'mollie_payments',
                'additionalData' => $additionalData,
                'shopContext' => $context->getVars(),
            ]
        ];

        $this->logEntryRepository->create([$logEntry], $context);
    }

}
