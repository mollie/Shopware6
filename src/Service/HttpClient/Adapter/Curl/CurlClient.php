<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\HttpClient\Adapter\Curl;

use Kiener\MolliePayments\Service\HttpClient\HttpClientInterface;
use Kiener\MolliePayments\Service\HttpClient\Response\HttpResponse;

class CurlClient implements HttpClientInterface
{
    /**
     * @var int
     */
    private $timeoutSeconds;

    public function __construct(int $timeoutSeconds)
    {
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function sendRequest(string $method, string $url, string $content = ''): HttpResponse
    {
        $handle = curl_init($url);

        assert($handle !== false);

        // required to follow any redirects and to get the response
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);

        // turn on SSL verification
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, true);

        // connect timeout needs to be as short as possible
        // this is just a ping if the server is reachable
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 1);

        // this is our real timeout for the request
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeoutSeconds);

        // set additional data
        curl_setopt($handle, CURLOPT_HTTPHEADER, []);
        curl_setopt($handle, CURLOPT_HEADER, true); // gets the header in the response

        /** @phpstan-ignore-next-line  */
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $method);

        if (strtoupper($method) === 'POST' || strtoupper($method) === 'PUT') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $content);
        }

        $response = $this->execute($handle);

        curl_close($handle);

        return $response;
    }

    /** @phpstan-ignore-next-line */
    private function execute($handle): HttpResponse
    {
        assert($handle !== false);
        $response = (string) curl_exec($handle);
        $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);

        $responseHeaders = substr($response, 0, $header_size);
        $responseBody = substr($response, $header_size);

        /**
         * @var ?int $statusCode
         */
        $statusCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        if ($statusCode === null) {
            $statusCode = 0;
        }

        return new HttpResponse($statusCode, $responseBody);
    }
}
