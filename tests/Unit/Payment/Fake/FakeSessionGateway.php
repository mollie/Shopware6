<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Session;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeSessionGateway implements SessionGatewayInterface
{
    public function __construct(private Session $session)
    {
    }

    public function createPaypalExpressSession(Cart $cart, SalesChannelContext $salesChannelContext): Session
    {
        return $this->session;
    }

    public function loadSession(string $sessionId, SalesChannelContext $salesChannelContext): Session
    {
        return $this->session;
    }

    public function cancelSession(string $sessionId, SalesChannelContext $salesChannelContext): Session
    {
        return $this->session;
    }
}
