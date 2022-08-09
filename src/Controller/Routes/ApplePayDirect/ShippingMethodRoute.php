<?php

namespace Kiener\MolliePayments\Controller\Routes\ApplePayDirect;

use Kiener\MolliePayments\Controller\Routes\ApplePayDirect\Struct\ApplePayShipping;
use Kiener\MolliePayments\Service\ApplePayDirect\ApplePayDirect;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\CustomerService;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ShippingMethodRoute
{

    /**
     * @var SettingsService
     */
    private $pluginSettings;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var ApplePayDirect
     */
    private $applePay;

    /**
     * @var CustomerService
     */
    private $customerService;


    /**
     * @param SettingsService $pluginSettings
     * @param CartService $cartService
     * @param ApplePayDirect $applePay
     * @param CustomerService $customerService
     */
    public function __construct(SettingsService $pluginSettings, CartService $cartService, ApplePayDirect $applePay, CustomerService $customerService)
    {
        $this->pluginSettings = $pluginSettings;
        $this->cartService = $cartService;
        $this->applePay = $applePay;
        $this->customerService = $customerService;
    }


    /**
     * @param string $countryCode
     * @param SalesChannelContext $context
     * @return ApplePayShipping
     * @throws \Exception
     */
    public function getShippingMethods(string $countryCode, SalesChannelContext $context): ApplePayShipping
    {
        if (empty($countryCode)) {
            throw new \Exception('No Country Code provided!');
        }

        $currentMethodID = $context->getShippingMethod()->getId();

        $countryID = $this->customerService->getCountryId($countryCode, $context->getContext());

        # get all available shipping methods of
        # our current country for Apple Pay
        $shippingMethods = $this->applePay->getShippingMethods($countryID, $context);

        # restore our previously used shipping method
        $context = $this->cartService->updateShippingMethod($context, $currentMethodID);

        # ...and get our calculated cart
        $swCart = $this->cartService->getCalculatedMainCart($context);
        $applePayCart = $this->applePay->buildApplePayCart($swCart);

        $isTestMode = $this->pluginSettings->getSettings($context->getSalesChannel()->getId())->isTestMode();

        $formattedCart = $this->applePay->format($applePayCart, $isTestMode, $context);

        return new ApplePayShipping(
            $formattedCart,
            $shippingMethods
        );
    }

}
