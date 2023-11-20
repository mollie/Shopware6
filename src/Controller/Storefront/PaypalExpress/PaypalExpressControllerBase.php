<?php

namespace Kiener\MolliePayments\Controller\Storefront\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomerServiceInterface;
use Kiener\MolliePayments\Traits\Storefront\RedirectTrait;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class PaypalExpressControllerBase extends StorefrontController
{


    use RedirectTrait;


    /**
     * @var PayPalExpress
     */
    private $paypalExpress;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var CustomerServiceInterface
     */
    private $customerService;

    /**
     * @param PayPalExpress $paypalExpress
     * @param CartService $cartService
     * @param RouterInterface $router
     * @param CustomerServiceInterface $customerService
     */
    public function __construct(PayPalExpress $paypalExpress, CartService $cartService, RouterInterface $router, CustomerServiceInterface $customerService)
    {
        $this->paypalExpress = $paypalExpress;
        $this->cartService = $cartService;
        $this->router = $router;
        $this->customerService = $customerService;
    }


    /**
     * @Route("/mollie/paypal-express/paypal-id", defaults={"csrf_protected"=true}, name="frontend.mollie.paypal.id", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return JsonResponse
     */
    public function getPaypalID(SalesChannelContext $context): JsonResponse
    {
        try {
            $id = $this->paypalExpress->getActivePaypalID($context);

            return new JsonResponse([
                'id' => $id,
            ]);
        } catch (\Throwable $ex) {
            # ! WE DO NOT LOG IN HERE!
            # otherwise we would always get logs if its just not enabled
            # so this is either a valid response or invalid one...that's it

            return new JsonResponse([
                'id' => 'not-found',
            ]);
        }
    }


    /**
     * @Route("/mollie/paypal-express/paypal-express-id", defaults={"csrf_protected"=true}, name="frontend.mollie.paypal-express.id", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return JsonResponse
     */
    public function getPaypalExpressID(SalesChannelContext $context): JsonResponse
    {
        try {
            $id = $this->paypalExpress->getActivePaypalExpressID($context);

            return new JsonResponse([
                'id' => $id,
            ]);
        } catch (\Throwable $ex) {
            # ! WE DO NOT LOG IN HERE!
            # otherwise we would always get logs if its just not enabled
            # so this is either a valid response or invalid one...that's it

            return new JsonResponse([
                'id' => 'not-found',
            ]);
        }
    }

    /**
     * @Route("/mollie/paypal-express/start", defaults={"csrf_protected"=true}, name="frontend.mollie.paypal-express.start", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return Response
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function startCheckout(SalesChannelContext $context): Response
    {
        $cart = $this->cartService->getCalculatedMainCart($context);

        $url = $this->paypalExpress->startSession($cart, $context);

        $url = '/mollie/paypal-express/finish';

        return new RedirectResponse($url);
    }

    /**
     * @Route("/mollie/paypal-express/finish", defaults={"csrf_protected"=true}, name="frontend.mollie.paypal-express.finish", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return Response
     */
    public function finishCheckout(SalesChannelContext $context): Response
    {
        # register account
        $newContext = $this->paypalExpress->prepareCustomer(
            'Mollie',
            'Mollie',
            'cd@dasistweb.de',
            'test',
            'test',
            'test',
            'DE',
            'token-123',
            $context
        );


        /** @var CustomerEntity $customer */
        $customer = $this->customerService->getCustomer(
            $newContext->getCustomerId(),
            $newContext->getContext()
        );

        $this->customerService->setPaypalExpress($customer, $context->getContext());

        # redirect to confirm page
        $returnUrl = $this->getCheckoutConfirmPage($this->router);

        return new RedirectResponse($returnUrl);
    }

}