<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Mollie\Session;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SessionBuilderInterface
{
    public function buildFromCart(Cart $cart, SalesChannelContext $salesChannelContext): Session;

    public function buildFromOrder(OrderEntity $order, SalesChannelContext $salesChannelContext): Session;
}
