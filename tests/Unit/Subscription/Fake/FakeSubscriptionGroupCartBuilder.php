<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\SubscriptionGroupCart;
use Mollie\Shopware\Component\Subscription\SubscriptionGroupCartBuilderInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;

final class FakeSubscriptionGroupCartBuilder implements SubscriptionGroupCartBuilderInterface
{
    /** @var list<array{intervalKey:string,billingAddressId:?string,shippingAddressId:?string}> */
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
     * @return list<array{intervalKey:string,billingAddressId:?string,shippingAddressId:?string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function buildGroupCart(
        OrderEntity $order,
        string $intervalKey,
        Context $context,
        ?string $billingAddressId = null,
        ?string $shippingAddressId = null
    ): ?SubscriptionGroupCart {
        $this->calls[] = [
            'intervalKey' => $intervalKey,
            'billingAddressId' => $billingAddressId,
            'shippingAddressId' => $shippingAddressId,
        ];

        return $this->response;
    }
}
