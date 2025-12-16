<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\PayPalExpress;

use Mollie\Shopware\Component\Payment\PayPalExpress\Route\AbstractCancelCheckoutRoute;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\AbstractFinishCheckoutRoute;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\AbstractStartCheckoutRoute;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\CancelCheckoutRoute;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\FinishCheckoutRoute;
use Mollie\Shopware\Component\Payment\PayPalExpress\Route\StartCheckoutRoute;
use Psr\Log\LoggerInterface;
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
    private const SNIPPET_ERROR = 'molliePayments.payments.paypalExpress.paymentError';

    public function __construct(
        #[Autowire(service: StartCheckoutRoute::class)]
        private AbstractStartCheckoutRoute $startCheckoutRoute,
        #[Autowire(service: FinishCheckoutRoute::class)]
        private AbstractFinishCheckoutRoute $finishCheckoutRoute,
        #[Autowire(service: CancelCheckoutRoute::class)]
        private AbstractCancelCheckoutRoute $cancelCheckoutRoute,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger,
    ) {
    }

    #[Route(name: 'frontend.mollie.paypal-express.start', path: '/mollie/paypal-express/start', methods: ['POST', 'GET'], options: ['seo' => false])]
    public function startCheckout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $response = $this->startCheckoutRoute->startCheckout($request, $salesChannelContext);

            return new RedirectResponse((string) $response->getRedirectUrl());
        } catch (\Throwable $exception) {
            $this->addFlash('danger', $this->trans(self::SNIPPET_ERROR));
            $this->logger->error(
                'Failed to start Paypal Express checkout',
                ['message' => $exception->getMessage()]
            );

            return $this->forwardToRoute('frontend.checkout.cart.page');
        }
    }

    #[Route(name: 'frontend.mollie.paypal-express.finish', path: '/mollie/paypal-express/finish', methods: ['POST', 'GET'], options: ['seo' => false])]
    public function finishCheckout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $response = $this->finishCheckoutRoute->finishCheckout($salesChannelContext);

            return $this->forwardToRoute('frontend.checkout.confirm.page');
        } catch (\Throwable $exception) {
            $this->addFlash('danger', $this->trans(self::SNIPPET_ERROR));
            $this->logger->error(
                'Failed to finish Paypal Express checkout',
                ['message' => $exception->getMessage()]
            );

            return $this->forwardToRoute('frontend.checkout.cart.page');
        }
    }

    #[Route(name: 'frontend.mollie.paypal-express.cancel', path: '/mollie/paypal-express/cancel', methods: ['POST', 'GET'], options: ['seo' => false])]
    public function cancelCheckout(Request $request, SalesChannelContext $salesChannelContext): Response
    {
        try {
            $response = $this->cancelCheckoutRoute->cancel($salesChannelContext);

            return $this->forwardToRoute('frontend.checkout.cart.page');
        } catch (\Throwable $exception) {
            $this->addFlash('danger', $this->trans(self::SNIPPET_ERROR));
            $this->logger->error(
                'Failed to cancel Paypal Express checkout',
                ['message' => $exception->getMessage()]
            );

            return $this->forwardToRoute('frontend.checkout.cart.page');
        }
    }
}
