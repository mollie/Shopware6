<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\HttpClient;

use Kiener\MolliePayments\Service\HttpClient\Adapter\Curl\CurlClient;

class PluginHttpClientFactory
{
    public function buildClient(): HttpClientInterface
    {
        return new CurlClient(10);
    }
}
