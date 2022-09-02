<?php

namespace Kiener\MolliePayments\Service\Logger;

use Kiener\MolliePayments\Service\Logger\Processors\AnonymousWebProcessor;
use Kiener\MolliePayments\Service\Logger\Services\URLAnonymizer;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Psr\Log\LoggerInterface;

/**
 * @phpstan-import-type Record from \Monolog\Logger
 */
class MollieLogger implements LoggerInterface
{

    /**
     * this is the channel name that will be
     * displayed in the backend. It must not contain spaces
     */
    const CHANNEL = 'Mollie';

    /**
     * @var UidProcessor
     */
    private $processorUid;

    /**
     * @var IntrospectionProcessor
     */
    private $processorIntrospection;

    /**
     * @var AnonymousWebProcessor
     */
    private $webProcessor;

    /**
     * @var string
     */
    private $sessionId;

    /**
     * @var Logger
     */
    private $logger;


    /**
     * @param string $filename
     * @param string $retentionDays
     * @param string $logLevel
     * @param string $sessionId
     */
    public function __construct(string $filename, string $retentionDays, string $logLevel, string $sessionId)
    {
        $this->sessionId = $sessionId;

        $this->processorUid = new UidProcessor();
        $this->processorIntrospection = new IntrospectionProcessor();

        $this->webProcessor = new AnonymousWebProcessor(
            new WebProcessor(),
            new URLAnonymizer()
        );

        # create a new file handler that creates a new file every day
        # it also makes sure, to only keep logs for the provided number of days
        /** @phpstan-ignore-next-line */
        $fileHandler = new RotatingFileHandler($filename, (int)$retentionDays, $logLevel);

        # create our monolog instance that will be used to log messages
        $this->logger = new Logger(self::CHANNEL, [$fileHandler]);
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array<mixed> $context
     * @return void
     */
    public function log($level, $message, array $context = [])
    {
    }

    /**
     * @param string $message
     * @param array<mixed> $context
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $record = $this->buildProcessorRecord((string)Logger::DEBUG);

        $this->logger->debug(
            $this->modifyMessage($message),
            /** @phpstan-ignore-next-line */
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array<mixed> $context
     * @return void
     */
    public function info($message, array $context = [])
    {
        $record = $this->buildProcessorRecord((string)Logger::INFO);

        $this->logger->info(
            $this->modifyMessage($message),
            /** @phpstan-ignore-next-line */
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array<mixed> $context
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $record = $this->buildProcessorRecord((string)Logger::NOTICE);

        $this->logger->notice(
            $this->modifyMessage($message),
            /** @phpstan-ignore-next-line */
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array<mixed> $context
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $record = $this->buildProcessorRecord((string)Logger::WARNING);

        $this->logger->warning(
            $this->modifyMessage($message),
            /** @phpstan-ignore-next-line */
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array<mixed> $context
     * @return void
     */
    public function error($message, array $context = [])
    {
        $record = $this->buildProcessorRecord((string)Logger::ERROR);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        /** @phpstan-ignore-next-line */
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        $this->logger->error(
            $this->modifyMessage($message),
            /** @phpstan-ignore-next-line */
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param string $message
     * @param array<mixed> $context
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $record = $this->buildProcessorRecord((string)Logger::CRITICAL);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        /** @phpstan-ignore-next-line */
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        $this->logger->critical(
            $this->modifyMessage($message),
            /** @phpstan-ignore-next-line */
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param string $message
     * @param array<mixed> $context
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $record = $this->buildProcessorRecord((string)Logger::ALERT);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        /** @phpstan-ignore-next-line */
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        $this->logger->alert(
            $this->modifyMessage($message),
            /** @phpstan-ignore-next-line */
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param string $message
     * @param array<mixed> $context
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $record = $this->buildProcessorRecord((string)Logger::EMERGENCY);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        /** @phpstan-ignore-next-line */
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        $this->logger->emergency(
            $this->modifyMessage($message),
            /** @phpstan-ignore-next-line */
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param string $logLevel
     * @return array<mixed>
     */
    private function buildProcessorRecord($logLevel): array
    {
        return [
            'level' => $logLevel,
            'extra' => []
        ];
    }

    /**
     * @param string $message
     * @return string
     */
    private function modifyMessage($message): string
    {
        $sessionPart = substr($this->sessionId, 0, 4) . '...';

        return $message . ' (Session: ' . $sessionPart . ')';
    }

    /**
     * @param array<mixed> $context
     * @phpstan-param  Record $record
     * @return array<mixed>
     */
    private function extendInfoData(array $context, array $record)
    {
        $additional = [
            'session' => $this->sessionId,
            'processors' => [
                'uid' => $this->processorUid->__invoke($record)['extra'],
                'web' => $this->webProcessor->__invoke($record)['extra'],
            ]
        ];

        return array_merge_recursive($context, $additional);
    }

    /**
     * @param array<mixed> $context
     * @param array<mixed> $introspection
     * @phpstan-param  Record $record
     * @return array<mixed>
     */
    private function extendErrorData(array $context, array $introspection, array $record): array
    {
        $additional = [
            'session' => $this->sessionId,
            'processors' => [
                'uid' => $this->processorUid->__invoke($record)['extra'],
                'web' => $this->webProcessor->__invoke($record)['extra'],
                'introspection' => $introspection,
            ]
        ];

        return array_merge_recursive($context, $additional);
    }
}
