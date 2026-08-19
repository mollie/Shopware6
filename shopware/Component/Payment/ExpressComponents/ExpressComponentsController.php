<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Placeholder endpoints for the express components session. Mollie requires a redirectUrl
 * when a session is created; the actual finish/cancel handling is not implemented yet.
 */
#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
final class ExpressComponentsController extends StorefrontController
{
    #[Route(name: 'frontend.mollie.express-components.finish', path: '/mollie/express-components/finish', methods: ['POST', 'GET'], options: ['seo' => false])]
    public function finishCheckout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        return $this->redirectToRoute('frontend.checkout.cart.page');
    }

    #[Route(name: 'frontend.mollie.express-components.cancel', path: '/mollie/express-components/cancel', methods: ['POST', 'GET'], options: ['seo' => false])]
    public function cancelCheckout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        return $this->redirectToRoute('frontend.checkout.cart.page');
    }
}
