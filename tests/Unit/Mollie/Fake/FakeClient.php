<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Fake;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

final class FakeClient extends Client
{
    public function __construct(private ResponseInterface $response)
    {
    }

    public function get($uri, array $options = []): ResponseInterface
    {
        return $this->response;
    }

    public function post($uri, array $options = []): ResponseInterface
    {
        return $this->response;
    }
}
