<?php

namespace Kiener\MolliePayments\Service\HttpClient;

use Kiener\MolliePayments\Service\HttpClient\Adapter\Curl\CurlClient;

class PluginHttpClientFactory
{
    /**
     * @return HttpClientInterface
     */
    public function buildClient(): HttpClientInterface
    {
        return new CurlClient(10);
    }
}
