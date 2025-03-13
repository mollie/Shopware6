<?php
declare(strict_types=1);

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

    public function __construct(int $status, string $body)
    {
        $this->statusCode = $status;
        $this->body = $body;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
