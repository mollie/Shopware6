<?php

namespace Kiener\MolliePayments\Controller\Storefront\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Struct\Address\AddressStruct;
use Kiener\MolliePayments\Traits\Storefront\RedirectTrait;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
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
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param PayPalExpress $paypalExpress
     * @param CartService $cartService
     * @param RouterInterface $router
     * @param LoggerInterface $logger
     */
    public function __construct(PayPalExpress $paypalExpress, CartService $cartService, RouterInterface $router, LoggerInterface $logger)
    {
        $this->paypalExpress = $paypalExpress;
        $this->cartService = $cartService;
        $this->router = $router;
        $this->logger = $logger;
    }

    /**
     * @Route("/mollie/paypal-express/start", defaults={"csrf_protected"=true}, name="frontend.mollie.paypal-express.start", options={"seo"="false"}, methods={"GET","POST"})
     *
     * @param SalesChannelContext $context
     * @throws \Mollie\Api\Exceptions\ApiException
     * @return Response
     */
    public function startCheckout(Request $request, SalesChannelContext $context): Response
    {
        $productId = $request->get('productId');
        $quantity = (int)$request->get('quantity', 1);
        if ($request->isMethod(Request::METHOD_POST) && $productId !== null) {
            $this->cartService->addProduct($productId, $quantity, $context);
        }

        $cart = $this->cartService->getCalculatedMainCart($context);

        $cartExtension = $cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);
        $oldSessionId = null;
        if ($cartExtension instanceof ArrayStruct) {
            $oldSessionId = $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY] ?? null;
        }

        if ($oldSessionId !== null) {
            $session = $this->paypalExpress->loadSession($oldSessionId, $context);
            ;
        } else {
            $session = $this->paypalExpress->startSession($cart, $context);

            $cartExtension = new ArrayStruct([
                CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY => $session->id
            ]);
            $cart->addExtension(CustomFieldsInterface::MOLLIE_KEY, $cartExtension);


            $this->cartService->persistCart($cart, $context);
        }

        $redirectUrl = $session->getRedirectUrl();
        if ($redirectUrl === null) {
            $redirectUrl = $this->getCheckoutCartPage($this->router);
        }

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @Route("/mollie/paypal-express/finish", defaults={"csrf_protected"=true}, name="frontend.mollie.paypal-express.finish", options={"seo"="false"}, methods={"GET"})
     *
     * @param SalesChannelContext $context
     * @return Response
     */
    public function finishCheckout(Request $request, SalesChannelContext $context): Response
    {
        $cart = $this->cartService->getCalculatedMainCart($context);
        $cartExtension = $cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);

        $payPalExpressSessionId = null;

        if ($cartExtension instanceof ArrayStruct) {
            $payPalExpressSessionId = $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY] ?? null;
        }


        $returnUrl = $this->getCheckoutCartPage($this->router);


        if ($payPalExpressSessionId === null) {
            $this->logger->error('Failed to finish checkout, session not exists');

            return new RedirectResponse($returnUrl);
        }

        $payPalExpressSession = $this->paypalExpress->loadSession($payPalExpressSessionId, $context);

        if ($payPalExpressSession->shippingAddress === null) {
            $this->logger->error('Failed to finish checkout, got session without shipping address', [
                'sessionId' => $payPalExpressSession->id,
                'status' => $payPalExpressSession->status
            ]);

            return new RedirectResponse($returnUrl);
        }

        $shippingAddress = AddressStruct::createFromApiResponse($payPalExpressSession->shippingAddress);

        $billingAddress = null;
        if ($payPalExpressSession->billingAddress instanceof \stdClass) {
            $billingAddress = AddressStruct::createFromApiResponse($payPalExpressSession->billingAddress);
        }

        # create new account or find existing and login
        $this->paypalExpress->prepareCustomer($shippingAddress, $context, $billingAddress);


        $cartExtension = new ArrayStruct([
            CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID => $payPalExpressSession->authenticationId
        ]);
        $cart->addExtension(CustomFieldsInterface::MOLLIE_KEY, $cartExtension);
        $this->cartService->updateCart($cart);

        $this->cartService->persistCart($cart, $context);

        $returnUrl = $this->getCheckoutConfirmPage($this->router);
        return new RedirectResponse($returnUrl);
    }
}
