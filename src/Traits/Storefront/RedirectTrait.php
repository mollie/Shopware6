<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Traits\Storefront;

use Symfony\Component\Routing\RouterInterface;

trait RedirectTrait
{
    /**
     * @param string $orderId
     * @param RouterInterface $router
     * @return string
     */
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

    /**
     * @param RouterInterface $router
     * @return string
     */
    public function getCheckoutConfirmPage(RouterInterface $router): string
    {
        return $router->generate(
            'frontend.checkout.confirm.page',
            [],
            $router::ABSOLUTE_URL
        );
    }

    /**
     * @param string $orderId
     * @param RouterInterface $router
     * @return string
     */
    public function getEditOrderPage(string $orderId, RouterInterface $router): string
    {
        return $router->generate(
            'frontend.account.edit-order.page',
            [
                'orderId' => $orderId
            ],
            $router::ABSOLUTE_URL
        );
    }
}
