<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\HttpClient;

use Kiener\MolliePayments\Service\HttpClient\Response\HttpResponse;

/**
 * Why do we need this?
 * There was a problem in Shopware 6.3.5.0 if we use the regular PSR interface
 * ...guzzle problems....so let's just do it on our own...it's not that bad :)
 */
interface HttpClientInterface
{
    public function sendRequest(string $method, string $url, string $content = ''): HttpResponse;
}
