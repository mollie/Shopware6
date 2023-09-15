<?php

namespace Kiener\MolliePayments\Service\HttpClient\Response;

class HttpResponse
{
    /**
     * @var int
     */
    private $statusCode;

    /**
     * @var string
     */
    private $body;

    /**
     * @param int $status
     * @param string $body
     */
    public function __construct(int $status, string $body)
    {
        $this->statusCode = $status;
        $this->body = $body;
    }

    /**
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getBody(): string
    {
        return $this->body;
    }
}
