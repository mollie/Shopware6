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
}
