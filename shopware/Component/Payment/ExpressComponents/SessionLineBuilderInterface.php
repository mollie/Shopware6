<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\LineItemCollection;
use Mollie\Shopware\Component\Mollie\Money;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SessionLineBuilderInterface
{
    public function build(Cart $cart, Money $amount, SalesChannelContext $salesChannelContext): LineItemCollection;

    public function buildFromOrder(OrderEntity $order, Money $amount, SalesChannelContext $salesChannelContext): LineItemCollection;
}
