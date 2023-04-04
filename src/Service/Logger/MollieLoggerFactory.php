<?php

namespace Kiener\MolliePayments\Service\Logger;

use Kiener\MolliePayments\Service\SettingsService;
use Monolog\Formatter\LogstashFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Session\Session;

class MollieLoggerFactory
{

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var ?Session
     */
    private $session;

    /**
     * @var string
     */
    private $filename;

    /**
     * @var string
     */
    private $retentionDays;


    /**
     * @param SettingsService $settingsService
     * @param ?Session $session
     * @param string $filename
     * @param string $retentionDays
     */
    public function __construct(SettingsService $settingsService, ?Session $session, string $filename, string $retentionDays)
    {
        $this->settingsService = $settingsService;
        $this->session = $session;
        $this->filename = $filename;
        $this->retentionDays = $retentionDays;
    }

    /**
     * @return MollieLogger
     */
    public function createLogger(): LoggerInterface
    {
        $config = $this->settingsService->getSettings();

        $sessionID = ($this->session !== null) ? $this->session->getId() : '-';

        $minLevel = Level::Info;

        if ($config->isDebugMode()) {
            $minLevel = Level::Debug;
        }

        return $this->buildLogger(
            'Mollie',
            [],
            $minLevel
        );
    }

    /**
     * @param string $channel
     * @param array $processors
     * @param null|Level $logLevel
     * @return LoggerInterface
     */
    private function buildLogger(string $channel, array $processors, ?Level $logLevel = null): LoggerInterface
    {
        if ($logLevel === null) {
            $logLevel = Level::Debug;
        }

        $logStashFormatter = new LogstashFormatter($channel, 'Shopware');

        $fileHandler = new RotatingFileHandler($this->filename, $this->retentionDays, $logLevel);
        $fileHandler->setFormatter($logStashFormatter);

        foreach ($processors as $processor) {
            $fileHandler->pushProcessor($processor);
        }

        return new Logger($channel, [$fileHandler]);
    }
}
