<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie\Gateway;

use Mollie\Shopware\Component\Mollie\Session;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface SessionGatewayInterface
{
    public function createPaypalExpressSession(Cart $cart, SalesChannelContext $salesChannelContext): Session;

    public function loadSession(string $sessionId, SalesChannelContext $salesChannelContext): Session;
}
