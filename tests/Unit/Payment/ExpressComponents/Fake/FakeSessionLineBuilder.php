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

    public function __construct(private LineItemCollection $lines = new LineItemCollection())
    {
    }

    /**
     * The amount the builder was asked to distribute over the lines. The session must not contain
     * the shipping costs, so this is what a test asserts the deduction against.
     */
    public function getLastAmount(): Money
    {
        if (! $this->lastAmount instanceof Money) {
            throw new \RuntimeException('FakeSessionLineBuilder was never called.');
        }

        return $this->lastAmount;
    }

    public function build(Cart $cart, Money $amount, SalesChannelContext $salesChannelContext): LineItemCollection
    {
        $this->lastAmount = $amount;

        return $this->lines;
    }

    public function buildFromOrder(OrderEntity $order, Money $amount, SalesChannelContext $salesChannelContext): LineItemCollection
    {
        $this->lastAmount = $amount;

        return $this->lines;
    }
}
