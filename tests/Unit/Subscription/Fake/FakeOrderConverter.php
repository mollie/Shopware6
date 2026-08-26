<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Subscription\Fake;

use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeOrderConverter extends OrderConverter
{
    /** @var list<array<string, mixed>> */
    private array $overrideOptions = [];

    public function __construct(private readonly SalesChannelContext $salesChannelContext)
    {
    }

    public function assembleSalesChannelContext(OrderEntity $order, Context $context, array $overrideOptions = []): SalesChannelContext
    {
        $this->overrideOptions[] = $overrideOptions;

        return $this->salesChannelContext;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLastOverrideOptions(): array
    {
        $last = end($this->overrideOptions);

        if ($last === false) {
            throw new \RuntimeException('No sales channel context has been assembled.');
        }

        return $last;
    }
}
