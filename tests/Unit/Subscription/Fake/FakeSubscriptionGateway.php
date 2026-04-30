<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Mollie\CreateSubscription;
use Mollie\Shopware\Component\Mollie\Gateway\SubscriptionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Subscription;

final class FakeSubscriptionGateway implements SubscriptionGatewayInterface
{
    /** @var array<string,Subscription> */
    private array $subscriptions = [];

    private ?Subscription $copyResponse = null;

    private ?Subscription $createResponse = null;

    /** @var list<CreateSubscription> */
    private array $createPayloads = [];

    /** @var list<array{method:string,subscriptionId:string,customerId:string,orderNumber:string,salesChannelId:string}> */
    private array $calls = [];

    public function register(Subscription $subscription): void
    {
        $this->subscriptions[$subscription->getId()] = $subscription;
    }

    public function setCopyResponse(Subscription $subscription): void
    {
        $this->copyResponse = $subscription;
        $this->subscriptions[$subscription->getId()] = $subscription;
    }

    public function setCreateResponse(Subscription $subscription): void
    {
        $this->createResponse = $subscription;
        $this->subscriptions[$subscription->getId()] = $subscription;
    }

    /**
     * @return list<CreateSubscription>
     */
    public function getCreatePayloads(): array
    {
        return $this->createPayloads;
    }

    public function getCallCount(string $method): int
    {
        $count = 0;
        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return list<array{method:string,subscriptionId:string,customerId:string,orderNumber:string,salesChannelId:string}>
     */
    public function getCalls(string $method): array
    {
        $calls = [];
        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    public function getSubscription(string $mollieSubscriptionId, string $customerId, string $orderNumber, string $salesChannelId): Subscription
    {
        $this->calls[] = [
            'method' => 'getSubscription',
            'subscriptionId' => $mollieSubscriptionId,
            'customerId' => $customerId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ];

        if (! isset($this->subscriptions[$mollieSubscriptionId])) {
            throw new \RuntimeException(sprintf('FakeSubscriptionGateway has no subscription registered for id "%s"', $mollieSubscriptionId));
        }

        return $this->subscriptions[$mollieSubscriptionId];
    }

    public function createSubscription(CreateSubscription $createSubscription, string $customerId, string $orderNumber, string $salesChannelId): Subscription
    {
        $this->createPayloads[] = $createSubscription;
        $this->calls[] = [
            'method' => 'createSubscription',
            'subscriptionId' => '',
            'customerId' => $customerId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ];

        if (! $this->createResponse instanceof Subscription) {
            throw new \RuntimeException('FakeSubscriptionGateway::createSubscription called without a configured create response. Use setCreateResponse() in the test.');
        }

        return $this->createResponse;
    }

    public function copySubscription(Subscription $mollieSubscription, string $customerId, string $orderNumber, string $salesChannelId): Subscription
    {
        $this->calls[] = [
            'method' => 'copySubscription',
            'subscriptionId' => $mollieSubscription->getId(),
            'customerId' => $customerId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ];

        if (! $this->copyResponse instanceof Subscription) {
            throw new \RuntimeException('FakeSubscriptionGateway::copySubscription called without a configured copy response. Use setCopyResponse() in the test.');
        }

        return $this->copyResponse;
    }

    public function cancelSubscription(string $mollieSubscriptionId, string $customerId, string $orderNumber, string $salesChannelId): Subscription
    {
        $this->calls[] = [
            'method' => 'cancelSubscription',
            'subscriptionId' => $mollieSubscriptionId,
            'customerId' => $customerId,
            'orderNumber' => $orderNumber,
            'salesChannelId' => $salesChannelId,
        ];

        if (! isset($this->subscriptions[$mollieSubscriptionId])) {
            throw new \RuntimeException(sprintf('FakeSubscriptionGateway has no subscription registered for id "%s"', $mollieSubscriptionId));
        }

        return $this->subscriptions[$mollieSubscriptionId];
    }

    public function updateSubscription(Subscription $mollieSubscription, string $customerId, string $orderNumber, string $salesChannelId): Subscription
    {
        throw new \LogicException('FakeSubscriptionGateway::updateSubscription not implemented');
    }
}
