<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\ExpressComponents\Fake;

use Mollie\Shopware\Component\Mollie\Session;
use Mollie\Shopware\Component\Payment\ExpressComponents\SessionBuilderInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeSessionBuilder implements SessionBuilderInterface
{
    private ?Cart $lastCart = null;
    private ?OrderEntity $lastOrder = null;

    private Session $cartSession;
    private Session $orderSession;

    public function __construct(?Session $cartSession = null, ?Session $orderSession = null)
    {
        $this->cartSession = $cartSession ?? self::session('ses_cart', 'token_cart');
        $this->orderSession = $orderSession ?? self::session('ses_order', 'token_order');
    }

    public function getLastCart(): Cart
    {
        if ($this->lastCart === null) {
            throw new \RuntimeException('FakeSessionBuilder::buildFromCart was never called.');
        }

        return $this->lastCart;
    }

    public function getLastOrder(): OrderEntity
    {
        if ($this->lastOrder === null) {
            throw new \RuntimeException('FakeSessionBuilder::buildFromOrder was never called.');
        }

        return $this->lastOrder;
    }

    public function wasCalled(): bool
    {
        return $this->lastCart !== null || $this->lastOrder !== null;
    }

    public function buildFromCart(Cart $cart, SalesChannelContext $salesChannelContext): Session
    {
        $this->lastCart = $cart;

        return $this->cartSession;
    }

    public function buildFromOrder(OrderEntity $order, SalesChannelContext $salesChannelContext): Session
    {
        $this->lastOrder = $order;

        return $this->orderSession;
    }

    private static function session(string $id, string $clientAccessToken): Session
    {
        $session = new Session($id);
        $session->setClientAccessToken($clientAccessToken);

        return $session;
    }
}
