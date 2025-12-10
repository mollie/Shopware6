<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ApplePayDirect;

use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractCreateSessionRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractGetCartRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractGetShippingMethodsRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\AbstractSetShippingCountryRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\CreateSessionRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetCartRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\GetShippingMethodsRoute;
use Mollie\Shopware\Component\Payment\ApplePayDirect\Route\SetShippingCountryRouteRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
final class ApplePayController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: CreateSessionRoute::class)]
        private AbstractCreateSessionRoute $createSessionRoute,
        #[Autowire(service: GetCartRoute::class)]
        private AbstractGetCartRoute $getCartRoute,
        #[Autowire(service: SetShippingCountryRouteRoute::class)]
        private AbstractSetShippingCountryRoute $setShippingCountryRoute,
        #[Autowire(service: GetShippingMethodsRoute::class)]
        private AbstractGetShippingMethodsRoute $getShippingMethodsRoute,
    ) {
    }

    #[Route(name: 'frontend.mollie.apple-pay.validate', path: '/mollie/apple-pay/validate', methods: ['POST'], options: ['seo' => false])]
    public function createSession(Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $session = '';
        $success = false;
        try {
            $response = $this->createSessionRoute->session($request, $salesChannelContext);
            $success = true;
            $session = $response->getSession();
        } catch (\Throwable $exception) {
        }

        return new JsonResponse([
            'success' => $success,
            'session' => json_encode($session),
        ]);
    }

    #[Route(name: 'frontend.mollie.apple-pay.shipping-methods', path: '/mollie/apple-pay/shipping-methods', methods: ['POST'], options: ['seo' => false])]
    public function updateShippingMethods(Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $success = false;
        $cart = [];
        $shippingMethods = [];
        try {
            $setShippingCountryResponse = $this->setShippingCountryRoute->setShippingCountry($request, $salesChannelContext);
            $salesChannelContext = $setShippingCountryResponse->getSalesChannelContext();
            $cartResponse = $this->getCartRoute->cart($request, $salesChannelContext);
            $shippingMethodsResponse = $this->getShippingMethodsRoute->methods($request,$cartResponse->getShopwareCart(),$salesChannelContext);

            $cart = $cartResponse->getCart();
            $shippingMethods = $shippingMethodsResponse->getShippingMethods();
            $success = true;
        } catch (\Throwable $exception) {
        }

        return new JsonResponse([
            'success' => $success,
            'cart' => $cart,
            'shippingMethods' => $shippingMethods,
        ]);
    }
}
