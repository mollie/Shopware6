<?php

namespace Kiener\MolliePayments\Service\Logger;

use Kiener\MolliePayments\Service\Logger\Processors\AnonymousWebProcessor;
use Kiener\MolliePayments\Service\Logger\Processors\SessionProcessor;
use Kiener\MolliePayments\Service\Logger\Services\URLAnonymizer;
use Kiener\MolliePayments\Service\SettingsService;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\WebProcessor;
use Psr\Log\LoggerInterface;

class MollieLoggerFactory
{
    /**
     * this is the channel name that will be
     * displayed in the backend. It must not contain spaces
     */
    const CHANNEL = 'Mollie';


    /**
     * @var SettingsService
     */
    private $settingsService;

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
     * @param string $filename
     * @param string $retentionDays
     */
    public function __construct(SettingsService $settingsService, string $filename, string $retentionDays)
    {
        $this->settingsService = $settingsService;
        $this->filename = $filename;
        $this->retentionDays = $retentionDays;
    }

    /**
     * @return LoggerInterface
     */
    public function createLogger(): LoggerInterface
    {
        $config = $this->settingsService->getSettings();

        # 100 = DEBUG, 200 = INFO
        $minLevel = ($config->isDebugMode()) ? 100 : 200;

        $fileHandler = new RotatingFileHandler($this->filename, (int)$this->retentionDays, $minLevel);

        $processors = [];
        $processors[] = new AnonymousWebProcessor(new WebProcessor(), new URLAnonymizer());
        $processors[] = new IntrospectionProcessor();

        /** @var callable $processor */
        foreach ($processors as $processor) {
            $fileHandler->pushProcessor($processor);
        }

        return new Logger(self::CHANNEL, [$fileHandler]);
    }
}
