<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\DAL\Subscription\SubscriptionEntity;
use Mollie\Shopware\Component\Subscription\RenewalAddresses;
use Mollie\Shopware\Component\Subscription\SubscriptionAddressSyncerInterface;
use Shopware\Core\Framework\Context;

final class FakeSubscriptionAddressSyncer implements SubscriptionAddressSyncerInterface
{
    /** @var list<array{subscriptionId:string}> */
    private array $calls = [];

    public function __construct(private RenewalAddresses $response = new RenewalAddresses('billing-id', 'shipping-id'))
    {
    }

    public function setResponse(RenewalAddresses $response): void
    {
        $this->response = $response;
    }

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    public function syncFromSubscription(SubscriptionEntity $subscription, Context $context): RenewalAddresses
    {
        $this->calls[] = ['subscriptionId' => $subscription->getId()];

        return $this->response;
    }
}
