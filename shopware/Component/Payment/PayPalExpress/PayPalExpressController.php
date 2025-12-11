<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => true])]
final class PayPalExpressController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: StartCheckoutRoute::class)]
        private AbstractStartCheckoutRoute $startCheckoutRoute,
        #[Autowire(service: FinishCheckoutRoute::class)]
        private AbstractFinishCheckoutRoute $finishCheckoutRoute,
    ) {
    }

    #[Route(name: 'frontend.mollie.paypal-express.start', path: '/mollie/paypal-express/start', methods: ['POST', 'GET'], options: ['seo' => false])]
    public function startCheckout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $response = $this->startCheckoutRoute->startCheckout($request, $salesChannelContext);

        return new RedirectResponse($response->getRedirectUrl());
    }

    #[Route(name: 'frontend.mollie.paypal-express.finish', path: '/mollie/paypal-express/finish', methods: ['POST', 'GET'], options: ['seo' => false])]
    public function finishCheckout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $response = $this->finishCheckoutRoute->finishCheckout($salesChannelContext);

        return $this->forwardToRoute('frontend.checkout.confirm.page');
    }

    #[Route(name: 'frontend.mollie.paypal-express.cancel', path: '/mollie/paypal-express/cancel', methods: ['POST', 'GET'], options: ['seo' => false])]
    public function cancelCheckout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        dump($request);

        return new Response();
    }
}
