<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

interface RetryMiddlewareInterface
{
    /**
     * Builds a Guzzle middleware that retries requests on connection problems
     * and server side errors (HTTP status >= 500).
     */
    public function createMiddleware(?int $maxRetries = null, ?int $baseDelayMs = null): callable;
}
