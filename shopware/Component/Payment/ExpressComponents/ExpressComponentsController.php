<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\Route\AbstractFinishCheckoutRoute;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutRoute;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Storefront endpoint for the express components session. It only translates between the
 * storefront and the checkout of FinishCheckoutRoute, which a headless setup reaches through
 * its api route instead, so both share the same implementation.
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
            $response = $this->finishCheckoutRoute->finishCheckout($request, $salesChannelContext);

            // the redirect of the Shopware payment handling, which runs the regular finalize and
            // ends on the order success or the edit order page
            $redirectUrl = $response->getRedirectUrl();
            if ($redirectUrl !== '') {
                return new RedirectResponse($redirectUrl);
            }

            return $this->redirectToRoute('frontend.checkout.finish.page', ['orderId' => $response->getOrderId()]);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to finish express components checkout', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelContext->getSalesChannelId(),
            ]);

            return $this->redirectToRoute('frontend.checkout.cart.page');
        }
    }
}
