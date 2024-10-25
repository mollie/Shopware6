<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\PaypalExpress\Route;

use Kiener\MolliePayments\Components\PaypalExpress\PayPalExpress;
use Kiener\MolliePayments\Components\PaypalExpress\PaypalExpressException;
use Kiener\MolliePayments\Service\CartServiceInterface;
use Kiener\MolliePayments\Service\CustomFieldsInterface;
use Kiener\MolliePayments\Service\SettingsService;
use Kiener\MolliePayments\Struct\Address\AddressStruct;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @final
 */
class FinishCheckoutRoute extends AbstractFinishCheckoutRoute
{
    private SettingsService $settingsService;
    private CartServiceInterface $cartService;
    private PayPalExpress $paypalExpress;


    public function __construct(
        SettingsService      $settingsService,
        CartServiceInterface $cartService,
        PayPalExpress        $paypalExpress
    ) {
        $this->settingsService = $settingsService;
        $this->cartService = $cartService;
        $this->paypalExpress = $paypalExpress;
    }

    public function getDecorated(): AbstractStartCheckoutRoute
    {
        throw new DecorationPatternException(self::class);
    }

    public function finishCheckout(SalesChannelContext $context): FinishCheckoutResponse
    {
        $settings = $this->settingsService->getSettings($context->getSalesChannelId());

        if ($settings->isPaypalExpressEnabled() === false) {
            throw PaypalExpressException::paymentNotEnabled($context->getSalesChannelId());
        }

        $cart = $this->cartService->getCalculatedMainCart($context);
        $cartExtension = $cart->getExtension(CustomFieldsInterface::MOLLIE_KEY);

        $payPalExpressSessionId = null;
        $acceptedDataProtection = null;

        if ($cartExtension instanceof ArrayStruct) {
            $payPalExpressSessionId = $cartExtension[CustomFieldsInterface::PAYPAL_EXPRESS_SESSION_ID_KEY] ?? null;
            if ($settings->isRequireDataProtectionCheckbox()) {
                $acceptedDataProtection = $cartExtension[CustomFieldsInterface::ACCEPTED_DATA_PROTECTION] ?? 0;
            }
        }


        if ($payPalExpressSessionId === null) {
            throw PaypalExpressException::cartSessionIdIsEmpty();
        }

        $payPalExpressSession = $this->paypalExpress->loadSession($payPalExpressSessionId, $context);


        $methodDetails = $payPalExpressSession->methodDetails;


        if (! property_exists($methodDetails, 'shippingAddress') || $methodDetails->shippingAddress === null) {
            throw PaypalExpressException::shippingAddressMissing();
        }
        if (! property_exists($methodDetails, 'billingAddress') || $methodDetails->billingAddress === null) {
            throw PaypalExpressException::billingAddressMissing();
        }

        $billingAddress = null;

        $mollieShippingAddress = $methodDetails->shippingAddress;
        if (! property_exists($mollieShippingAddress, 'phone')) {
            $mollieShippingAddress->phone = '';
        }
        if (! property_exists($mollieShippingAddress, 'streetAdditional')) {
            $mollieShippingAddress->streetAdditional = '';
        }
        if (! property_exists($mollieShippingAddress, 'email')) {
            $mollieShippingAddress->email = '';
        }

        if ($methodDetails->billingAddress->streetAdditional !== null) {
            $mollieShippingAddress->streetAdditional = $methodDetails->billingAddress->streetAdditional;
        }
        if ($methodDetails->billingAddress->phone !== null) {
            $mollieShippingAddress->phone = $methodDetails->billingAddress->phone;
        }
        if ($methodDetails->billingAddress->email !== null) {
            $mollieShippingAddress->email = $methodDetails->billingAddress->email;
        }
        if ($methodDetails->billingAddress->streetAndNumber !== null) {
            try {
                $billingAddress = AddressStruct::createFromApiResponse($methodDetails->billingAddress);
            } catch (\Throwable $e) {
                throw PaypalExpressException::billingAddressError(
                    $e->getMessage(),
                    $methodDetails->billingAddress
                );
            }
        }

        try {
            $shippingAddress = AddressStruct::createFromApiResponse($mollieShippingAddress);
        } catch (\Throwable $e) {
            throw PaypalExpressException::shippingAddressError(
                $e->getMessage(),
                $mollieShippingAddress
            );
        }

        # we have to update the cart extension before a new user is created and logged in, otherwise the extension is not saved
        $cartExtension = new ArrayStruct([
            CustomFieldsInterface::PAYPAL_EXPRESS_AUTHENTICATE_ID => $payPalExpressSession->authenticationId
        ]);
        $cart->addExtension(CustomFieldsInterface::MOLLIE_KEY, $cartExtension);

        $this->cartService->updateCart($cart);

        $this->cartService->persistCart($cart, $context);


        # create new account or find existing and login
        $this->paypalExpress->prepareCustomer($shippingAddress, $context, $acceptedDataProtection, $billingAddress);
        return new FinishCheckoutResponse($payPalExpressSession->id, $payPalExpressSession->authenticationId);
    }
}
