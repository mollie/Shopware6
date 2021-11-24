<?php

namespace Kiener\MolliePayments\Service\Logger\Processors;

use Kiener\MolliePayments\Service\Logger\Services\IPAnonymizer;
use Kiener\MolliePayments\Service\Logger\Services\URLAnonymizer;
use Monolog\Processor\WebProcessor;


class AnonymousWebProcessor
{

    /**
     * @var WebProcessor
     */
    private $webProcessor;

    /**
     * @var IPAnonymizer
     */
    private $ipAnonymizer;

    /**
     * @var URLAnonymizer
     */
    private $urlAnonymizer;


    /**
     * @param WebProcessor $webProcessor
     * @param IPAnonymizer $ipAnonymizer
     * @param URLAnonymizer $urlAnonymizer
     */
    public function __construct(WebProcessor $webProcessor, IPAnonymizer $ipAnonymizer, URLAnonymizer $urlAnonymizer)
    {
        $this->webProcessor = $webProcessor;
        $this->ipAnonymizer = $ipAnonymizer;
        $this->urlAnonymizer = $urlAnonymizer;
    }

    /**
     * @param array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        $record = $this->webProcessor->__invoke($record);

        if (array_key_exists('ip', $record['extra'])) {

            # get the original IP
            $originalIP = $record['extra']['ip'];

            # replace it with our anonymous IP
            $record['extra']['ip'] = $this->ipAnonymizer->anonymize($originalIP);
        }

        if (array_key_exists('url', $record['extra'])) {

            $url = $record['extra']['url'];

            $record['extra']['url'] = $this->urlAnonymizer->anonymize($url);
        }

        return $record;
    }

}
