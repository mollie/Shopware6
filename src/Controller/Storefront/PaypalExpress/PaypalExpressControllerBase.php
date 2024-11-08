<?php

namespace Kiener\MolliePayments\Controller\Storefront\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\Route\AbstractCancelCheckoutRoute;
use Kiener\MolliePayments\Components\PaypalExpress\Route\AbstractFinishCheckoutRoute;
use Kiener\MolliePayments\Components\PaypalExpress\Route\AbstractStartCheckoutRoute;
use Kiener\MolliePayments\Traits\Storefront\RedirectTrait;
use Mollie\Api\Exceptions\ApiException;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class PaypalExpressControllerBase extends StorefrontController
{
    use RedirectTrait;
    private const SNIPPET_ERROR = 'molliePayments.payments.paypalExpress.paymentError';
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var LoggerInterface
     */
    private $logger;
    private AbstractStartCheckoutRoute $startCheckoutRoute;
    private AbstractFinishCheckoutRoute $finishCheckoutRoute;
    private AbstractCancelCheckoutRoute $cancelCheckoutRoute;


    /**
     * @param AbstractStartCheckoutRoute $startCheckoutRoute
     * @param AbstractFinishCheckoutRoute $finishCheckoutRoute
     * @param AbstractCancelCheckoutRoute $cancelCheckoutRoute
     * @param RouterInterface $router
     * @param LoggerInterface $logger
     */
    public function __construct(AbstractStartCheckoutRoute $startCheckoutRoute, AbstractFinishCheckoutRoute $finishCheckoutRoute, AbstractCancelCheckoutRoute $cancelCheckoutRoute, RouterInterface $router, LoggerInterface $logger)
    {
        $this->router = $router;
        $this->logger = $logger;
        $this->startCheckoutRoute = $startCheckoutRoute;
        $this->finishCheckoutRoute = $finishCheckoutRoute;
        $this->cancelCheckoutRoute = $cancelCheckoutRoute;
    }

    /**
     * @param Request $request
     * @param SalesChannelContext $context
     * @throws ApiException
     * @return Response
     */
    public function startCheckout(Request $request, SalesChannelContext $context): Response
    {
        $redirectUrl = $this->getCheckoutCartPage($this->router);

        try {
            $response = $this->startCheckoutRoute->startCheckout($request, $context);
            $responseRedirectUrl = $response->getRedirectUrl();

            if ($responseRedirectUrl !== null) {
                $redirectUrl = $responseRedirectUrl;
            }
        } catch (\Throwable $e) {
            $this->addFlash('danger', $this->trans(self::SNIPPET_ERROR));
            $this->logger->error(
                'Failed to start Paypal Express checkout',
                ['message' => $e->getMessage()]
            );
        }
        return new RedirectResponse($redirectUrl);
    }

    public function cancelCheckout(SalesChannelContext $context): Response
    {
        $redirectUrl = $this->getCheckoutCartPage($this->router);

        try {
            $this->cancelCheckoutRoute->cancelCheckout($context);
        } catch (\Throwable $e) {
            $this->addFlash('danger', $this->trans(self::SNIPPET_ERROR));
            $this->logger->error(
                'Failed to cancel Paypal Express checkout',
                ['message' => $e->getMessage()]
            );
        }
        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param SalesChannelContext $context
     * @return Response
     */
    public function finishCheckout(SalesChannelContext $context): Response
    {
        try {
            $this->finishCheckoutRoute->finishCheckout($context);

            $returnUrl = $this->getCheckoutConfirmPage($this->router);
        } catch (\Throwable $e) {
            $returnUrl = $this->getCheckoutCartPage($this->router);
            $this->addFlash('danger', $this->trans(self::SNIPPET_ERROR));
            $this->logger->error(
                'Failed to finish Paypal Express Checkout',
                ['message' => $e->getMessage()]
            );
        }
        return new RedirectResponse($returnUrl);
    }
}
