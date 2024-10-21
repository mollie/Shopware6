<?php

namespace Kiener\MolliePayments\Controller\Storefront\PaypalExpress;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\Address\AddressStruct;
use Kiener\MolliePayments\Traits\Storefront\RedirectTrait;
use Mollie\Api\Exceptions\ApiException;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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
     * @var LoggerInterface
     */
    private $logger;
    private SettingsService $settingsService;


    /**
     * @param PayPalExpress $paypalExpress
     * @param CartService $cartService
     * @param RouterInterface $router
     * @param LoggerInterface $logger
     */
    public function __construct(PayPalExpress $paypalExpress, CartService $cartService, RouterInterface $router, SettingsService $settingsService, LoggerInterface $logger)
    {
        $this->paypalExpress = $paypalExpress;
        $this->cartService = $cartService;
        $this->router = $router;
        $this->logger = $logger;
        $this->settingsService = $settingsService;
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

        $settings = $this->settingsService->getSettings($context->getSalesChannelId());

        if ($settings->isPaypalExpressEnabled() === false) {
            $this->logger->error('Paypal Express is disabled');
            return new RedirectResponse($redirectUrl);
        }

        $cart = $this->cartService->getCalculatedMainCart($context);


        $cartExtension = $cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);
        $oldSessionId = null;

        if ($cartExtension instanceof ArrayStruct) {
            $oldSessionId = $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY] ?? null;
        }

        if ($oldSessionId !== null) {
            $session = $this->paypalExpress->loadSession($oldSessionId, $context);
        } else {
            $session = $this->paypalExpress->startSession($cart, $context);

            $cartExtension = [
                CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY => $session->id
            ];

            if ($settings->isRequireDataProtectionCheckbox()) {
                $cartExtension[CustomFieldsInterface::ACCEPTED_DATA_PROTECTION] = (bool)$request->get(CustomFieldsInterface::ACCEPTED_DATA_PROTECTION, false);
            }

            $cart->addExtension(CustomFieldsInterface::MOLLIE_KEY, new ArrayStruct($cartExtension));

            $this->cartService->persistCart($cart, $context);
        }

        $sessionRedirect = $session->getRedirectUrl();
        if ($sessionRedirect !== null) {
            $this->logger->error('Paypal Express redirect URL is empty', [
                'sessionId' => $session->id,
            ]);
            $redirectUrl = $sessionRedirect;
        }

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param SalesChannelContext $context
     * @return Response
     */
    public function finishCheckout(SalesChannelContext $context): Response
    {
        $returnUrl = $this->getCheckoutCartPage($this->router);

        $settings = $this->settingsService->getSettings($context->getSalesChannelId());

        if ($settings->isPaypalExpressEnabled() === false) {
            $this->logger->error('Paypal Express is disabled');
            return new RedirectResponse($returnUrl);
        }

        $cart = $this->cartService->getCalculatedMainCart($context);
        $cartExtension = $cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);

        $payPalExpressSessionId = null;
        $acceptedDataProtection = null;

        if ($cartExtension instanceof ArrayStruct) {
            $payPalExpressSessionId = $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY] ?? null;
            if ($settings->isRequireDataProtectionCheckbox()) {
                $acceptedDataProtection = $cartExtension[CustomFieldsInterface::ACCEPTED_DATA_PROTECTION] ?? false;
            }
        }





        if ($payPalExpressSessionId === null) {
            $this->logger->error('Failed to finish checkout, session not exists');

            return new RedirectResponse($returnUrl);
        }

        try {
            $payPalExpressSession = $this->paypalExpress->loadSession($payPalExpressSessionId, $context);
        } catch (\Throwable $e) {
            $this->logger->critical('Failed to load session from mollie', [
                'message' => $e->getMessage(),
                'sessionId' => $payPalExpressSessionId
            ]);
            return new RedirectResponse($returnUrl);
        }


        $methodDetails = $payPalExpressSession->methodDetails;


        if ($methodDetails->shippingAddress === null) {
            $this->logger->error('Failed to finish checkout, got methodDetails without shipping address', [
                'sessionId' => $payPalExpressSession->id,
                'status' => $payPalExpressSession->status
            ]);

            return new RedirectResponse($returnUrl);
        }
        if ($methodDetails->billingAddress === null) {
            $this->logger->error('Failed to finish checkout, got methodDetails without billing address', [
                'sessionId' => $payPalExpressSession->id,
                'status' => $payPalExpressSession->status
            ]);

            return new RedirectResponse($returnUrl);
        }

        $billingAddress = null;

        $shippingAddress = $methodDetails->shippingAddress;
        $shippingAddress->phone = '';
        if ($methodDetails->billingAddress->streetAdditional !== null) {
            $shippingAddress->streetAdditional = $methodDetails->billingAddress->streetAdditional;
        }
        if ($methodDetails->billingAddress->phone !== null) {
            $shippingAddress->phone = $methodDetails->billingAddress->phone;
        }
        if ($methodDetails->billingAddress->email !== null) {
            $shippingAddress->email = $methodDetails->billingAddress->email;
        }
        if ($methodDetails->billingAddress->streetAndNumber !== null) {
            try {
                $billingAddress = AddressStruct::createFromApiResponse($methodDetails->billingAddress);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to create billing address', [
                    'message' => $e->getMessage(),
                    'shippingAddress' => $shippingAddress,
                    'billingAddress' => $methodDetails->billingAddress
                ]);
                return new RedirectResponse($returnUrl);
            }
        }

        try {
            $shippingAddress = AddressStruct::createFromApiResponse($shippingAddress);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create shipping address', [
                'message' => $e->getMessage(),
                'shippingAddress' => $shippingAddress,
                'billingAddress' => $methodDetails->billingAddress
            ]);
            return new RedirectResponse($returnUrl);
        }


        try {
            # we have to update the cart extension before a new user is created and logged in, otherwise the extension is not saved
            $cartExtension = new ArrayStruct([
                CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID => $payPalExpressSession->authenticationId
            ]);
            $cart->addExtension(CustomFieldsInterface::MOLLIE_KEY, $cartExtension);

            $this->cartService->updateCart($cart);

            $this->cartService->persistCart($cart, $context);


            # create new account or find existing and login
            $this->paypalExpress->prepareCustomer($shippingAddress, $context, $acceptedDataProtection, $billingAddress);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create customer or cart', [
                'message' => $e->getMessage(),
            ]);
            return new RedirectResponse($returnUrl);
        }


        $returnUrl = $this->getCheckoutConfirmPage($this->router);
        return new RedirectResponse($returnUrl);
    }
}
