<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Payment\Route\WebhookResponse;
use Mollie\Shopware\Component\Subscription\Route\AbstractWebhookRoute;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

final class FakeWebhookRoute extends AbstractWebhookRoute
{
    /** @var list<array{subscriptionId:string}> */
    private array $calls = [];

    private ?WebhookResponse $response = null;

    private ?\Throwable $exceptionToThrow = null;

    public function setResponse(WebhookResponse $response): void
    {
        $this->response = $response;
    }

    public function setException(\Throwable $exception): void
    {
        $this->exceptionToThrow = $exception;
    }

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    /**
     * @return list<array{subscriptionId:string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function getDecorated(): AbstractWebhookRoute
    {
        throw new \LogicException('FakeWebhookRoute::getDecorated not implemented');
    }

    public function notify(string $subscriptionId, Request $request, Context $context): WebhookResponse
    {
        $this->calls[] = ['subscriptionId' => $subscriptionId];

        if ($this->exceptionToThrow instanceof \Throwable) {
            throw $this->exceptionToThrow;
        }

        if (! $this->response instanceof WebhookResponse) {
            throw new \RuntimeException('FakeWebhookRoute::notify called without configured response. Use setResponse() in the test.');
        }

        return $this->response;
    }
}
