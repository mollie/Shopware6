<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Traits\Storefront;

use Symfony\Component\Routing\RouterInterface;

trait RedirectTrait
{
    public function getCheckoutFinishPage(string $orderId, RouterInterface $router): string
    {
        return $router->generate(
            'frontend.checkout.finish.page',
            [
                'orderId' => $orderId,
            ],
            $router::ABSOLUTE_URL
        );
    }

    public function getCheckoutConfirmPage(RouterInterface $router): string
    {
        return $router->generate(
            'frontend.checkout.confirm.page',
            [],
            $router::ABSOLUTE_URL
        );
    }

    public function getEditOrderPage(string $orderId, RouterInterface $router): string
    {
        return $router->generate(
            'frontend.account.edit-order.page',
            [
                'orderId' => $orderId,
            ],
            $router::ABSOLUTE_URL
        );
    }

    public function getCheckoutCartPage(RouterInterface $router): string
    {
        return $router->generate('frontend.checkout.cart.page', [], $router::ABSOLUTE_URL);
    }
}
