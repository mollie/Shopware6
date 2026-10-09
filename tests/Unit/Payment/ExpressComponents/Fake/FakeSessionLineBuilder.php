<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Fake;

use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionLineBuilderInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeSessionLineBuilder implements SessionLineBuilderInterface
{
    private ?Money $lastAmount = null;
    private ?bool $lastWithShippingLines = null;

    public function __construct(private LineItemCollection $lines = new LineItemCollection())
    {
    }

    public function getLastAmount(): Money
    {
        if (! $this->lastAmount instanceof Money) {
            throw new \RuntimeException('FakeSessionLineBuilder was never called.');
        }

        return $this->lastAmount;
    }

    public function wasLastAskedForShippingLines(): bool
    {
        if ($this->lastWithShippingLines === null) {
            throw new \RuntimeException('FakeSessionLineBuilder::build() was never called.');
        }

        return $this->lastWithShippingLines;
    }

    public function build(Cart $cart, Money $amount, bool $withShippingLines, SalesChannelContext $salesChannelContext): LineItemCollection
    {
        $this->lastAmount = $amount;
        $this->lastWithShippingLines = $withShippingLines;

        return $this->lines;
    }

    public function buildFromOrder(OrderEntity $order, Money $amount, SalesChannelContext $salesChannelContext): LineItemCollection
    {
        $this->lastAmount = $amount;

        return $this->lines;
    }
}
