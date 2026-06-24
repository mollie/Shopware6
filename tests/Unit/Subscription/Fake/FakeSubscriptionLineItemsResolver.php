<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Mollie\Shopware\Component\Subscription\SubscriptionLineItemsResolverInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeSubscriptionLineItemsResolver implements SubscriptionLineItemsResolverInterface
{
    /** @var list<array{orderId:string}> */
    private array $calls = [];

    public function __construct(private Collection $lineItems = new LineItemCollection())
    {
    }

    public function setLineItems(Collection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

    public function getCallCount(): int
    {
        return count($this->calls);
    }

    /**
     * @return list<array{orderId:string}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    public function resolveLineItems(string $orderId, SalesChannelContext $salesChannelContext): Collection
    {
        $this->calls[] = ['orderId' => $orderId];

        return $this->lineItems;
    }
}
