<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Mollie\Subscription;
use Mollie\Shopware\Component\Subscription\SubscriptionActionHandlerInterface;
use Shopware\Core\Framework\Context;

final class FakeSubscriptionActionHandler implements SubscriptionActionHandlerInterface
{
    /** @var list<array{action:string,subscriptionId:string}> */
    private array $calls = [];

    private ?Subscription $response = null;

    private ?\Throwable $exceptionToThrow = null;

    /** @var list<class-string> */
    private array $eventClasses = [];

    public function setResponse(Subscription $response): void
    {
        $this->response = $response;
    }

    public function setException(\Throwable $exception): void
    {
        $this->exceptionToThrow = $exception;
    }

    /**
     * @param list<class-string> $eventClasses
     */
    public function setActionEvents(array $eventClasses): void
    {
        $this->eventClasses = $eventClasses;
    }

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    /**
     * @return list<array{action:string,subscriptionId:string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function handle(string $action, string $subscriptionId, Context $context): Subscription
    {
        $this->calls[] = [
            'action' => $action,
            'subscriptionId' => $subscriptionId,
        ];

        if ($this->exceptionToThrow instanceof \Throwable) {
            throw $this->exceptionToThrow;
        }

        if (! $this->response instanceof Subscription) {
            throw new \RuntimeException('FakeSubscriptionActionHandler::handle called without configured response. Use setResponse() in the test.');
        }

        return $this->response;
    }

    /**
     * @return array<class-string>
     */
    public function getActionEvents(): array
    {
        return $this->eventClasses;
    }
}
