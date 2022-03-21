<?php

namespace Kiener\MolliePayments\Service\Logger\Processors;

use Kiener\MolliePayments\Service\Logger\Services\URLAnonymizerInterface;
use Monolog\Processor\ProcessorInterface;
use Symfony\Component\HttpFoundation\IpUtils;

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
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $record = $this->webProcessor->__invoke($record);

        if (array_key_exists('extra', $record)) {
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
