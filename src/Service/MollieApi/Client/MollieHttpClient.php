<?php

namespace Kiener\MolliePayments\Service\MollieApi\Client;

use Composer\CaBundle\CaBundle;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\HttpAdapter\MollieHttpAdapterInterface;
use Mollie\Api\MollieApiClient;

class MollieHttpClient implements MollieHttpAdapterInterface
{
    /**
     * @var int in seconds
     */
    private $connnectTimeout;

    /**
     * @var int in seconds
     */
    private $responseTimeout;

    /**
     * HTTP status code for an empty ok response.
     */
    const HTTP_NO_CONTENT = 204;


    /**
     * @param int $connnectTimeout
     * @param int $responseTimeout
     */
    public function __construct($connnectTimeout, $responseTimeout)
    {
        $this->connnectTimeout = $connnectTimeout;
        $this->responseTimeout = $responseTimeout;
    }


    /**
     * @param string $httpMethod
     * @param string $url
     * @param array<mixed> $headers
     * @param string $httpBody
     * @throws ApiException
     * @return null|\stdClass
     */
    public function send($httpMethod, $url, $headers, $httpBody)
    {
        $curl = curl_init($url);

        assert($curl !== false);

        $headers["Content-Type"] = "application/json";

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->parseHeaders($headers));
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->connnectTimeout);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->responseTimeout);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_CAINFO, CaBundle::getBundledCaBundlePath());

        switch ($httpMethod) {
            case MollieApiClient::HTTP_POST:
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $httpBody);

                break;
            case MollieApiClient::HTTP_GET:
                break;
            case MollieApiClient::HTTP_PATCH:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PATCH');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $httpBody);

                break;
            case MollieApiClient::HTTP_DELETE:
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $httpBody);

                break;
            default:
                throw new \InvalidArgumentException("Invalid http method: " . $httpMethod);
        }

        $response = curl_exec($curl);

        if ($response === false) {
            throw new ApiException("Curl error: " . curl_error($curl));
        }

        $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        curl_close($curl);

        /** @var string $response */
        return $this->parseResponseBody($response, $statusCode, $httpBody);
    }

    /**
     * The version number for the underlying http client, if available.
     * @return null|string
     * @example Guzzle/6.3
     *
     */
    public function versionString()
    {
        return 'Curl/*';
    }

    /**
     * @param string $response
     * @param int $statusCode
     * @param string $httpBody
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return null|\stdClass
     */
    protected function parseResponseBody($response, $statusCode, $httpBody)
    {
        if (empty($response)) {
            if ($statusCode === self::HTTP_NO_CONTENT) {
                return null;
            }

            throw new ApiException("No response body found.");
        }

        $body = @json_decode($response);

        // GUARDS
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException("Unable to decode Mollie response: '{$response}'.");
        }

        if (isset($body->error)) {
            throw new ApiException($body->error->message);
        }

        if ($statusCode >= 400) {
            $message = "Error executing API call ({$body->status}: {$body->title}): {$body->detail}";

            $field = null;

            if (!empty($body->field)) {
                $field = $body->field;
            }

            if (isset($body->_links, $body->_links->documentation)) {
                $message .= ". Documentation: {$body->_links->documentation->href}";
            }
            
            throw new ApiException($message, $statusCode, $field);
        }

        return $body;
    }

    /**
     * @param array<mixed> $headers
     * @return array<mixed>
     */
    protected function parseHeaders($headers)
    {
        $result = [];

        foreach ($headers as $key => $value) {
            $result[] = $key . ': ' . $value;
        }

        return $result;
    }
}
