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
     * @param $filename
     * @param $retentionDays
     * @param $logLevel
     * @param $sessionId
     */
    public function __construct($filename, $retentionDays, $logLevel, $sessionId)
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
        $fileHandler = new RotatingFileHandler($filename, $retentionDays, $logLevel);

        # create our monolog instance that will be used to log messages
        $this->logger = new Logger(self::CHANNEL, [$fileHandler]);
    }

    /**
     * @param mixed $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = [])
    {
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::DEBUG);

        $this->logger->debug(
            $this->modifyMessage($message),
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::INFO);

        $this->logger->info(
            $this->modifyMessage($message),
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function notice($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::NOTICE);

        $this->logger->notice(
            $this->modifyMessage($message),
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::WARNING);

        $this->logger->warning(
            $this->modifyMessage($message),
            $this->extendInfoData($context, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::ERROR);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        $this->logger->error(
            $this->modifyMessage($message),
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function critical($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::CRITICAL);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        $this->logger->critical(
            $this->modifyMessage($message),
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function alert($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::ALERT);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        $this->logger->alert(
            $this->modifyMessage($message),
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param string $message
     * @param array $context
     * @return void
     */
    public function emergency($message, array $context = [])
    {
        $record = $this->buildProcessorRecord(Logger::EMERGENCY);

        # we have to run introspection exactly 1 function hierarchy
        # below our actual call. so lets do it here
        $introspection = $this->processorIntrospection->__invoke($record)['extra'];

        $this->logger->emergency(
            $this->modifyMessage($message),
            $this->extendErrorData($context, $introspection, $record)
        );
    }

    /**
     * @param $logLevel
     * @return array
     */
    private function buildProcessorRecord($logLevel)
    {
        return [
            'level' => $logLevel,
            'extra' => []
        ];
    }

    /**
     * @param $message
     * @return string
     */
    private function modifyMessage($message)
    {
        $sessionPart = substr($this->sessionId, 0, 4) . '...';

        return $message . ' (Session: ' . $sessionPart . ')';
    }

    /**
     * @param array $context
     * @param array $record
     * @return array
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
     * @param array $context
     * @param array $introspection
     * @param array $record
     * @return array
     */
    private function extendErrorData(array $context, array $introspection, array $record)
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
