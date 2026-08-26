<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Fake;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class FakeHttpKernel implements HttpKernelInterface
{
    private ?Request $handledRequest = null;

    private ?\Throwable $failure = null;

    public function __construct(private Response $response = new Response('forwarded'))
    {
    }

    /**
     * What the forwarded controller throws, e.g. an invalid payment token.
     */
    public function withFailure(\Throwable $failure): void
    {
        $this->failure = $failure;
    }

    public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
    {
        $this->handledRequest = $request;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->response;
    }

    public function getHandledRequest(): Request
    {
        if ($this->handledRequest === null) {
            throw new \RuntimeException('The kernel has not handled a request.');
        }

        return $this->handledRequest;
    }
}
