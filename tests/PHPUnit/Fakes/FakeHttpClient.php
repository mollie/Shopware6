<?php
declare(strict_types=1);

namespace MolliePayments\Tests\Fakes;

use Kiener\MolliePayments\Service\HttpClient\HttpClientInterface;
use Kiener\MolliePayments\Service\HttpClient\Response\HttpResponse;

class FakeHttpClient implements HttpClientInterface
{
    public function sendRequest(string $method, string $url, string $content = ''): HttpResponse
    {
        return new HttpResponse(200, '');
    }
}
