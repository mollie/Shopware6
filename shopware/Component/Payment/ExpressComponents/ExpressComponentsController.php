<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\Route\AbstractFinishCheckoutRoute;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutRoute;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Storefront endpoints for the express components session. They only translate between the
 * storefront and the store-api routes, so both the classic and a headless setup share the
 * same implementation.
 */
#[Route(defaults: ['_routeScope' => ['storefront'], 'csrf_protected' => false])]
final class ExpressComponentsController extends StorefrontController
{
    public function __construct(
        #[Autowire(service: FinishCheckoutRoute::class)]
        private AbstractFinishCheckoutRoute $finishCheckoutRoute,
        #[Autowire(service: ShippingOptionsResolver::class)]
        private ShippingOptionsResolverInterface $shippingOptionsResolver,
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

    /**
     * Called by Mollie whenever the shopper picks a different address inside the express
     * component, so the shipping options can be recalculated for that address. This is a
     * server to server call from Mollie, therefore a storefront route and not store-api.
     *
     * Request:  {"sessionId": "sess_...", "shippingAddress": {"postalCode": "...", "city": "...", "region": "...", "country": "NL"}}
     * Response: {"shippingOptions": [{"description": "...", "reference": "...", "amount": {"currency": "EUR", "value": "3.99"}}]}
     */
    #[Route(name: 'frontend.mollie.express-components.shipping-options', path: '/mollie/express-components/shipping-options', methods: ['POST'], defaults: ['auth_required' => false], options: ['seo' => false])]
    public function shippingOptions(Request $request, SalesChannelContext $salesChannelContext): JsonResponse
    {
        $salesChannelId = $salesChannelContext->getSalesChannelId();

        try {
            $body = json_decode((string) $request->getContent(), true);
            $body = is_array($body) ? $body : [];

            $sessionId = (string) ($body['sessionId'] ?? '');
            $shippingAddress = $body['shippingAddress'] ?? [];
            $address = ShippingCallbackAddress::fromArray(is_array($shippingAddress) ? $shippingAddress : []);

            $this->logger->info('Express components shipping options requested', [
                'sessionId' => $sessionId,
                'requestParameter' => $body,
                'salesChannelId' => $salesChannelId,
            ]);

            if ($address->getCountry() === '') {
                return new JsonResponse(['shippingOptions' => []]);
            }

            $shippingOptions = $this->shippingOptionsResolver->resolve($address, $salesChannelContext);

            return new JsonResponse(['shippingOptions' => $shippingOptions->toArray()]);
        } catch (\Throwable $exception) {
            $this->logger->error('Failed to resolve express components shipping options', [
                'error' => $exception->getMessage(),
                'salesChannelId' => $salesChannelId,
            ]);

            return new JsonResponse(['shippingOptions' => []]);
        }
    }
}
