<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Fake;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

final class FakeApiExceptionClient extends Client
{
    public function get($uri, array $options = []): ResponseInterface
    {
        $response = new Response(500,body: json_encode([
            'title' => 'Test',
            'detail' => 'Test',
            'field' => 'field',
        ]));
        $request = new Request('GET', $uri, $options);
        throw new ClientException('FakeApiExceptionClient', $request, $response);
    }

    public function post($uri, array $options = []): ResponseInterface
    {
    }
}
