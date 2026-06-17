<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\SubscriptionDataServiceInterface;
use Mollie\Shopware\Component\Subscription\SubscriptionDataStruct;
use Shopware\Core\Framework\Context;

final class FakeSubscriptionDataService implements SubscriptionDataServiceInterface
{
    /** @var list<array{subscriptionId:string}> */
    private array $calls = [];

    public function __construct(private ?SubscriptionDataStruct $struct = null)
    {
    }

    public function setStruct(SubscriptionDataStruct $struct): void
    {
        $this->struct = $struct;
    }

    /**
     * @return list<array{subscriptionId:string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function findById(string $subscriptionId, Context $context): SubscriptionDataStruct
    {
        $this->calls[] = ['subscriptionId' => $subscriptionId];

        if (! $this->struct instanceof SubscriptionDataStruct) {
            throw new \RuntimeException('FakeSubscriptionDataService::findById called without configured struct.');
        }

        return $this->struct;
    }
}
