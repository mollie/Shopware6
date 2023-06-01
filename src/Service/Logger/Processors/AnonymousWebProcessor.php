<?php

namespace Kiener\MolliePayments\Service\Logger\Processors;

use Kiener\MolliePayments\Service\Logger\Services\URLAnonymizerInterface;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * @phpstan-import-type Record from \Monolog\Logger
 */
class AnonymousWebProcessor
{

    /**
     * @var ProcessorInterface
     */
    private $webProcessor;

    /**
     * @var URLAnonymizerInterface
     */
    private $urlAnonymizer;


    /**
     * @param ProcessorInterface $webProcessor
     * @param URLAnonymizerInterface $urlAnonymizer
     */
    public function __construct(ProcessorInterface $webProcessor, URLAnonymizerInterface $urlAnonymizer)
    {
        $this->webProcessor = $webProcessor;
        $this->urlAnonymizer = $urlAnonymizer;
    }

    /**
     * attention, if we just skip the data type (array/LogRecord) then it
     * works for both monolog versions (old and new) for Shopware 6.4 and 6.5.
     *
     * @phpstan-param  Record $record
     * @param mixed $record
     * @return array<mixed>
     */
    public function __invoke($record)
    {
        $record = $this->webProcessor->__invoke($record);

        /** @phpstan-ignore-next-line */
        if (isset($record['extra'])) {
            if (array_key_exists('ip', $record['extra'])) {
                # replace it with our anonymous IP
                $record['extra']['ip'] = IpUtils::anonymize(trim($record['extra']['ip']));
            }

            if (array_key_exists('url', $record['extra'])) {
                $record['extra']['url'] = $this->urlAnonymizer->anonymize($record['extra']['url']);
            }
        }

        return $record;
    }
}
