<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

interface RetryMiddlewareInterface
{
    /**
     * Builds a Guzzle middleware that retries requests on connection problems
     * and server side errors (HTTP status >= 500).
     * POST requests get an Idempotency-Key that stays the same for all retries.
     */
    public function createMiddleware(?int $maxRetries = null, ?int $baseDelayMs = null): callable;
}
