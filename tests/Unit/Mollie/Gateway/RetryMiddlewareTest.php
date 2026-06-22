<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mollie\Shopware\Component\Mollie\Gateway\RetryMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(RetryMiddleware::class)]
final class RetryMiddlewareTest extends TestCase
{
    public function testRetriesOnServerError(): void
    {
        $middleware = new RetryMiddleware(new NullLogger());
        $request = new Request('GET', 'payments');
        $exception = new ServerException('Server error', $request, new Response(503));

        $actual = $middleware->shouldRetry(0, 3, null, $exception);

        $this->assertTrue($actual);
    }

    public function testRetriesOnConnectException(): void
    {
        $middleware = new RetryMiddleware(new NullLogger());
        $request = new Request('GET', 'payments');
        $exception = new ConnectException('Connection refused', $request);

        $actual = $middleware->shouldRetry(0, 3, null, $exception);

        $this->assertTrue($actual);
    }

    public function testDoesNotRetryOnClientError(): void
    {
        $middleware = new RetryMiddleware(new NullLogger());
        $response = new Response(422);

        $actual = $middleware->shouldRetry(0, 3, $response, null);

        $this->assertFalse($actual);
    }

    public function testDoesNotRetryOnSuccess(): void
    {
        $middleware = new RetryMiddleware(new NullLogger());
        $response = new Response(200);

        $actual = $middleware->shouldRetry(0, 3, $response, null);

        $this->assertFalse($actual);
    }

    public function testDoesNotRetryWhenMaxRetriesReached(): void
    {
        $middleware = new RetryMiddleware(new NullLogger());
        $request = new Request('GET', 'payments');
        $exception = new ServerException('Server error', $request, new Response(503));

        $actual = $middleware->shouldRetry(3, 3, null, $exception);

        $this->assertFalse($actual);
    }

    public function testCalculateDelayUsesExponentialBackoff(): void
    {
        $middleware = new RetryMiddleware(new NullLogger());

        $this->assertSame(500, $middleware->calculateDelay(1, 500));
        $this->assertSame(1000, $middleware->calculateDelay(2, 500));
        $this->assertSame(2000, $middleware->calculateDelay(3, 500));
    }

    public function testRequestSucceedsAfterTransientServerErrors(): void
    {
        $request = new Request('GET', 'payments');
        $mockHandler = new MockHandler([
            new ServerException('Server error', $request, new Response(503)),
            new ServerException('Server error', $request, new Response(503)),
            new Response(200, [], (string) json_encode(['id' => 'tr_test'])),
        ]);

        $client = $this->createClient($mockHandler);
        $response = $client->get('payments');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(0, $mockHandler);
    }

    public function testRequestFailsAfterRetriesAreExhausted(): void
    {
        $this->expectException(ServerException::class);
        $request = new Request('GET', 'payments');
        $mockHandler = new MockHandler([
            new ServerException('Server error', $request, new Response(503)),
            new ServerException('Server error', $request, new Response(503)),
            new ServerException('Server error', $request, new Response(503)),
            new ServerException('Server error', $request, new Response(503)),
        ]);

        $client = $this->createClient($mockHandler);
        $client->get('payments');
    }

    private function createClient(MockHandler $mockHandler): Client
    {
        $middleware = new RetryMiddleware(new NullLogger());
        $retryMiddleware = $middleware->createMiddleware(3, 0);
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push($retryMiddleware);

        return new Client(['handler' => $handlerStack]);
    }
}
