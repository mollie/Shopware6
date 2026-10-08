<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class RetryMiddleware implements RetryMiddlewareInterface
{
    private const DEFAULT_MAX_RETRIES = 3;

    private const DEFAULT_BASE_DELAY_MS = 500;

    /**
     * Mollie is unreachable / has a server side problem from this status code on.
     * Everything below (e.g. 4xx) is a client error and must not be retried.
     */
    private const RETRYABLE_STATUS_CODE = 500;

    private const IDEMPOTENCY_CONFLICT_STATUS_CODE = 409;

    private const IDEMPOTENCY_HEADER = 'Idempotency-Key';

    public function __construct(
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    public function createMiddleware(?int $maxRetries = null, ?int $baseDelayMs = null): callable
    {
        $maxRetries = $maxRetries ?? self::DEFAULT_MAX_RETRIES;
        $baseDelayMs = $baseDelayMs ?? self::DEFAULT_BASE_DELAY_MS;

        $decider = function (int $retries, RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $exception = null) use ($maxRetries): bool {
            return $this->shouldRetry($retries, $maxRetries, $response, $exception, $request);
        };

        $delay = function (int $retries) use ($baseDelayMs): int {
            return $this->calculateDelay($retries, $baseDelayMs);
        };

        $retry = Middleware::retry($decider, $delay);
        $idempotency = Middleware::mapRequest(function (RequestInterface $request): RequestInterface {
            return $this->addIdempotencyKey($request);
        });

        return function (callable $handler) use ($retry, $idempotency): callable {
            return $idempotency($retry($handler));
        };
    }

    public function shouldRetry(int $retries, int $maxRetries, ?ResponseInterface $response, ?\Throwable $exception, ?RequestInterface $request = null): bool
    {
        if ($retries >= $maxRetries) {
            return false;
        }

        $statusCode = $this->resolveStatusCode($response, $exception);
        if ($statusCode === self::IDEMPOTENCY_CONFLICT_STATUS_CODE && $request instanceof RequestInterface && $request->hasHeader(self::IDEMPOTENCY_HEADER)) {
            $this->logger->warning('Mollie API is still processing the original request, retrying request', [
                'attempt' => $retries + 1,
                'maxRetries' => $maxRetries,
                'statusCode' => $statusCode,
            ]);

            return true;
        }

        if ($exception instanceof ConnectException) {
            $this->logger->warning('Mollie API connection failed, retrying request', [
                'attempt' => $retries + 1,
                'maxRetries' => $maxRetries,
                'error' => $exception->getMessage(),
            ]);

            return true;
        }

        if ($statusCode >= self::RETRYABLE_STATUS_CODE) {
            $this->logger->warning('Mollie API returned a server error, retrying request', [
                'attempt' => $retries + 1,
                'maxRetries' => $maxRetries,
                'statusCode' => $statusCode,
            ]);

            return true;
        }

        return false;
    }

    public function calculateDelay(int $retries, int $baseDelayMs): int
    {
        return $baseDelayMs * (2 ** ($retries - 1));
    }

    private function addIdempotencyKey(RequestInterface $request): RequestInterface
    {
        if ($request->getMethod() !== 'POST' || $request->hasHeader(self::IDEMPOTENCY_HEADER)) {
            return $request;
        }

        return $request->withHeader(self::IDEMPOTENCY_HEADER, Uuid::randomHex());
    }

    private function resolveStatusCode(?ResponseInterface $response, ?\Throwable $exception): int
    {
        if ($response instanceof ResponseInterface) {
            return $response->getStatusCode();
        }

        if ($exception instanceof BadResponseException) {
            return $exception->getResponse()->getStatusCode();
        }

        return 0;
    }
}
