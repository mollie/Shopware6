<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Mollie\Gateway;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
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

    public function testRetriedPostRequestKeepsIdempotencyKey(): void
    {
        $request = new Request('POST', 'payments');
        $mockHandler = new MockHandler([
            new ConnectException('Connection refused', $request),
            new ServerException('Server error', $request, new Response(503)),
            new Response(201),
        ]);
        $history = [];

        $client = $this->createClient($mockHandler, $history);
        $client->post('payments');

        $this->assertCount(3, $history);
        $keys = array_map(static fn (array $transaction): string => $transaction['request']->getHeaderLine('Idempotency-Key'), $history);
        $this->assertNotSame('', $keys[0]);
        $this->assertSame([$keys[0], $keys[0], $keys[0]], $keys);
    }

    public function testEachPostRequestGetsOwnIdempotencyKey(): void
    {
        $mockHandler = new MockHandler([
            new Response(201),
            new Response(201),
        ]);
        $history = [];

        $client = $this->createClient($mockHandler, $history);
        $client->post('payments');
        $client->post('payments');

        $this->assertNotSame($history[0]['request']->getHeaderLine('Idempotency-Key'), $history[1]['request']->getHeaderLine('Idempotency-Key'));
    }

    public function testGetRequestHasNoIdempotencyKey(): void
    {
        $mockHandler = new MockHandler([
            new Response(200),
        ]);
        $history = [];

        $client = $this->createClient($mockHandler, $history);
        $client->get('payments');

        $this->assertFalse($history[0]['request']->hasHeader('Idempotency-Key'));
    }

    public function testRetriesPostRequestWhenOriginalIsStillProcessing(): void
    {
        $mockHandler = new MockHandler([
            new Response(409),
            new Response(201, ['Idempotent-Replayed' => 'true']),
        ]);
        $history = [];

        $client = $this->createClient($mockHandler, $history);
        $response = $client->post('payments');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertCount(2, $history);
    }

    public function testDoesNotRetryConflictWithoutIdempotencyKey(): void
    {
        $middleware = new RetryMiddleware(new NullLogger());
        $request = new Request('PATCH', 'payments/tr_test');

        $actual = $middleware->shouldRetry(0, 3, new Response(409), null, $request);

        $this->assertFalse($actual);
    }

    /**
     * @param array<int, array<string, mixed>> $history
     */
    private function createClient(MockHandler $mockHandler, array &$history = []): Client
    {
        $middleware = new RetryMiddleware(new NullLogger());
        $retryMiddleware = $middleware->createMiddleware(3, 0);
        $handlerStack = HandlerStack::create($mockHandler);
        $handlerStack->push($retryMiddleware);
        $handlerStack->push(Middleware::history($history));

        return new Client(['handler' => $handlerStack, 'http_errors' => false]);
    }
}
