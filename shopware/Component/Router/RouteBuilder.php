<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Router;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

final class RouteBuilder implements RouteBuilderInterface
{
    public function __construct(private RouterInterface $router, private RequestStack $requestStack)
    {
    }

    public function getReturnUrl(string $transactionId): string
    {
        $routeName = 'store-api.mollie.payment.return';
        $routeName = 'storefront.mollie.payment.return';

        return $this->router->generate($routeName, ['transactionId' => $transactionId], RouterInterface::ABSOLUTE_URL);
    }

    public function getWebhookUrl(string $transactionId): string
    {
        $routeName = 'store-api.mollie.payment.webhook';
        $routeName = 'storefront.mollie.payment.webhook';

        return $this->router->generate($routeName, ['transactionId' => $transactionId], RouterInterface::ABSOLUTE_URL);
    }
}
