<?php

namespace Kiener\MolliePayments\Controller\Storefront\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomerServiceInterface;
use Kiener\MolliePayments\Struct\Address\AddressStruct;
use Kiener\MolliePayments\Traits\Storefront\RedirectTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class PaypalExpressControllerBase extends StorefrontController
{


    use RedirectTrait;

    private const SESSION_ID_KEY = 'mollie_ppe_session_id';
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
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param PayPalExpress $paypalExpress
     * @param CartService $cartService
     * @param RouterInterface $router
     * @param CustomerServiceInterface $customerService
     */
    public function __construct(PayPalExpress $paypalExpress, CartService $cartService, RouterInterface $router, CustomerServiceInterface $customerService, LoggerInterface $logger)
    {
        $this->paypalExpress = $paypalExpress;
        $this->cartService = $cartService;
        $this->router = $router;
        $this->customerService = $customerService;
        $this->logger = $logger;
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
     * @Route("/mollie/paypal-express/start", defaults={"csrf_protected"=true}, name="frontend.mollie.paypal-express.start", options={"seo"="false"}, methods={"GET","POST"})
     *
     * @param SalesChannelContext $context
     * @return Response
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function startCheckout(Request $request, SalesChannelContext $context): Response
    {
        $productId = $request->get('productId');
        $quantity = (int)$request->get('quantity',1);
        if($request->isMethod(Request::METHOD_POST) && $productId !== null){
            $this->cartService->addProduct($productId,$quantity,$context);
        }

        $cart = $this->cartService->getCalculatedMainCart($context);

        $session = $this->paypalExpress->startSession($cart, $context);
        $request->getSession()->set(self::SESSION_ID_KEY, $session->id);

        return new RedirectResponse($session->getRedirectUrl());
    }

    /**
     * @Route("/mollie/paypal-express/finish", defaults={"csrf_protected"=true}, name="frontend.mollie.paypal-express.finish", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return Response
     */
    public function finishCheckout(Request $request, SalesChannelContext $context): Response
    {
        $payPalExpressSessionId = $request->getSession()->get(self::SESSION_ID_KEY);

        $returnUrl = $this->getCheckoutCartPage($this->router);


        if($payPalExpressSessionId === null){

            $this->logger->error('Failed to finish checkout, session not exists');

            return new RedirectResponse($returnUrl);
        }

        $payPalExpressSession = $this->paypalExpress->loadSession($payPalExpressSessionId, $context);
        if($payPalExpressSession->shippingAddress === null){

            $this->logger->error('Failed to finish checkout, got session without shipping address');

            return new RedirectResponse($returnUrl);
        }
        $shippingAddress = AddressStruct::createFromApiResponse($payPalExpressSession->shippingAddress);
        $billingAddress = null;
        if ($payPalExpressSession->billingAddress instanceof \stdClass) {
            $billingAddress = AddressStruct::createFromApiResponse($payPalExpressSession->billingAddress);
        }

        # create new account or find existing and login
        $newContext = $this->paypalExpress->prepareCustomer($shippingAddress, $context, $billingAddress);

        /** @var CustomerEntity $customer */
        $customer = $this->customerService->getCustomer(
            $newContext->getCustomerId(),
            $newContext->getContext()
        );


        $this->customerService->setPaypalExpress($customer, $payPalExpressSession->authenticationId, $context->getContext());

        $returnUrl = $this->getCheckoutConfirmPage($this->router);
        return new RedirectResponse($returnUrl);
    }

}