<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\Route\AbstractFinishCheckoutRoute;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutRoute;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Storefront endpoints for the express components session. They only translate between the
 * storefront and the store-api routes, so both the classic and a headless setup share the
 * same implementation.
 *
 * The shipping callback is not here: Mollie calls it without credentials, so it lives in the
 * api scope with authentication disabled, the same way the webhooks do.
 */
#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
final class ExpressComponentsController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: FinishCheckoutRoute::class)]
        private AbstractFinishCheckoutRoute $finishCheckoutRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    /**
     * Mollie redirects the shopper here once the payment inside the express component is
     * done. The cart the session belongs to is identified by the cartToken query parameter.
     */
    #[Route(name: 'frontend.mollie.express-components.finish', path: '/mollie/express-components/finish', methods: ['POST', 'GET'], options: ['seo' => false])]
    public function finishCheckout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $this->finishCheckoutRoute->finishCheckout($request, $salesChannelContext);

            return $this->redirectToRoute('frontend.checkout.confirm.page');
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to finish express components checkout', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            ]);

            return $this->redirectToRoute('frontend.checkout.cart.page');
        }
    }

    #[Route(name: 'frontend.mollie.express-components.cancel', path: '/mollie/express-components/cancel', methods: ['POST', 'GET'], options: ['seo' => false])]
    public function cancelCheckout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        return $this->redirectToRoute('frontend.checkout.cart.page');
    }
}
