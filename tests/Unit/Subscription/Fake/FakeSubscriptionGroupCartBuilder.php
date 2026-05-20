<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\RenewalAddresses;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCart;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCartBuilderInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class FakeSubscriptionGroupCartBuilder implements SubscriptionGroupCartBuilderInterface
{
    /** @var list<array{intervalKey:string,addresses:?RenewalAddresses}> */
    private array $calls = [];

    public function __construct(private ?SubscriptionGroupCart $response = null)
    {
    }

    public function setResponse(?SubscriptionGroupCart $response): void
    {
        $this->response = $response;
    }

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    /**
     * @return list<array{intervalKey:string,addresses:?RenewalAddresses}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function buildGroupCart(
        OrderEntity $order,
        string $intervalKey,
        Context $context,
        ?RenewalAddresses $addresses = null
    ): ?SubscriptionGroupCart {
        $this->calls[] = [
            'intervalKey' => $intervalKey,
            'addresses' => $addresses,
        ];

        return $this->response;
    }
}
