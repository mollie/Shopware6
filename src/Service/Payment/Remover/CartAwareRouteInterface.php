<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Payment\Remover;

interface CartAwareRouteInterface
{
    public const CART_ROUTES = [
        'frontend.checkout.cart.page',
        'frontend.checkout.confirm.page',
        'frontend.checkout.finish.page',
        'frontend.cart.offcanvas',
        'store-api.payment.method',
    ];

    public function isCartRoute(): bool;

    public function getCart(\Shopware\Core\System\SalesChannel\SalesChannelContext $context): \Shopware\Core\Checkout\Cart\Cart;
}
