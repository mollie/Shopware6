<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Mollie\Shopware\Component\Payment\ExpressComponents\Route\AbstractFinishCheckoutRoute;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\AbstractSessionWebhookRoute;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\FinishCheckoutRoute;
use Mollie\Shopware\Component\Payment\ExpressComponents\Route\SessionWebhookRoute;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        #[Autowire(service: SessionWebhookRoute::class)]
        private AbstractSessionWebhookRoute $sessionWebhookRoute,
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

    #[Route(name: 'frontend.mollie.webhook.session', path: '/mollie/session/webhook/{webhookId}', methods: ['GET', 'POST'], options: ['seo' => false])]
    public function sessionWebhook(string $webhookId, Request $request, Context $context): Response
    {
        try {
            $response = $this->sessionWebhookRoute->notify($webhookId, $request, $context);

            return new JsonResponse($response->getObject());
        } catch (ShopwareHttpException $exception) {
            $this->logger->warning('Express components session webhook failed with warning', [
                'webhookId' => $webhookId,
                'message' => $exception->getMessage(),
            ]);

            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            $this->logger->error('Express components session webhook failed', [
                'webhookId' => $webhookId,
                'message' => $exception->getMessage(),
            ]);

            return new JsonResponse(['success' => false, 'error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
