<?php

namespace Kiener\MolliePayments\Service\Logger;


use Kiener\MolliePayments\Service\SettingsService;
use Monolog\Handler\RotatingFileHandler;
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
     * @var Session
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
     * @param Session $session
     * @param $filename
     * @param $retentionDays
     */
    public function __construct(SettingsService $settingsService, Session $session, $filename, $retentionDays)
    {
        $this->settingsService = $settingsService;
        $this->session = $session;
        $this->filename = $filename;
        $this->retentionDays = $retentionDays;
    }

    /**
     * @return MollieLogger
     */
    public function createLogger()
    {
        $config = $this->settingsService->getSettings();

        $sessionID = $this->session->getId();

        $minLevel = LogLevel::INFO;

        if ($config->isDebugMode()) {
            $minLevel = LogLevel::DEBUG;
        }

        return new MollieLogger(
            $this->filename,
            $this->retentionDays,
            $minLevel,
            $sessionID
        );
    }

}
