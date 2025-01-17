<?php

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Service\HttpClient\HttpClientInterface;
use Kiener\MolliePayments\Service\HttpClient\Response\HttpResponse;

class FakeHttpClient implements HttpClientInterface
{
    /**
     * @param string $method
     * @param string $url
     * @param string $content
     * @return HttpResponse
     */
    public function sendRequest(string $method, string $url, string $content = ''): HttpResponse
    {
        return new HttpResponse(200, '');
    }
}
