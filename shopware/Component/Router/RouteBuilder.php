<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Router;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

final class RouteBuilder implements RouteBuilderInterface
{
    public function __construct(
        #[Autowire(service: 'router.default')]
        private RouterInterface $router,
        #[Autowire(service: 'request_stack')]
        private RequestStack $requestStack)
    {
    }

    public function getReturnUrl(string $transactionId): string
    {
        $routeName = 'storefront.mollie.payment.return';

        if ($this->isStoreApiRequest()) {
            $routeName = 'store-api.mollie.payment.return';
        }

        return $this->router->generate($routeName, ['transactionId' => $transactionId], RouterInterface::ABSOLUTE_URL);
    }

    public function getWebhookUrl(string $transactionId): string
    {
        $routeName = 'storefront.mollie.payment.webhook';
        if ($this->isStoreApiRequest()) {
            $routeName = 'store-api.mollie.payment.webhook';
        }

        return $this->router->generate($routeName, ['transactionId' => $transactionId], RouterInterface::ABSOLUTE_URL);
    }

    private function isStoreApiRequest(): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return false;
        }

        return str_starts_with($request->getPathInfo(), '/store-api');
    }
}
