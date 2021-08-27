<?php

namespace Kiener\MolliePayments\Service\ApplePayDirect;

use Kiener\MolliePayments\Service\ApplePayDirect\Models\ApplePayCart;
use Kiener\MolliePayments\Service\ApplePayDirect\Services\ApplePayFormatter;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\ShippingMethodService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Translation\TranslatorInterface;

class ApplePayDirect
{

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var ShippingMethodService
     */
    private $shippingMethods;

    /**
     * @var ApplePayFormatter
     */
    private $formatter;


    /**
     * @param CartService $cartService
     * @param ShippingMethodService $shippingMethodService
     * @param TranslatorInterface $translator
     */
    public function __construct(CartService $cartService, ShippingMethodService $shippingMethodService, TranslatorInterface $translator)
    {
        $this->cartService = $cartService;
        $this->shippingMethods = $shippingMethodService;

        $this->formatter = new ApplePayFormatter($translator);
    }


    /**
     * @param Cart $cart
     * @return ApplePayCart
     */
    public function buildApplePayCart(Cart $cart): ApplePayCart
    {
        $appleCart = new ApplePayCart();

        /** @var LineItem $item */
        foreach ($cart->getLineItems() as $item) {

            $grossPrice = $item->getPrice()->getUnitPrice();

            $appleCart->addItem(
                $item->getReferencedId(),
                $item->getLabel(),
                $item->getQuantity(),
                $grossPrice
            );
        }

        /** @var Delivery $delivery */
        foreach ($cart->getDeliveries() as $delivery) {

            $grossPrice = $delivery->getShippingCosts()->getUnitPrice();

            if ($grossPrice > 0) {
                $appleCart->addShipping(
                    $delivery->getShippingMethod()->getName(),
                    $grossPrice
                );
            }
        }

        $taxes = $cart->getPrice()->getCalculatedTaxes()->getAmount();

        if ($taxes > 0) {
            $appleCart->setTaxes($taxes);
        }

        return $appleCart;
    }

    /**
     * @param string $countryCode
     * @param SalesChannelContext $context
     * @return array
     * @throws \Exception
     */
    public function getShippingMethods(string $countryID, SalesChannelContext $context): array
    {
        $currentMethodID = $context->getShippingMethod()->getId();


        # switch to the correct country of the apple pay user
        $context = $this->cartService->updateCountry($context, $countryID);

        $selectedMethod = null;
        $allMethods = [];

        $availableShippingMethods = $this->shippingMethods->getActiveShippingMethods($context);

        /** @var ShippingMethodEntity $method */
        foreach ($availableShippingMethods as $method) {

            # temporary switch to our shipping method.
            # we will then load the cart for this shipping method
            # in order to get the calculated shipping costs for this.
            $tempContext = $this->cartService->updateShippingMethod($context, $method->getId());
            $tempCart = $this->cartService->getCalculatedMainCart($tempContext);

            $shippingCosts = $this->cartService->getShippingCosts($tempCart);

            # format it for apple pay
            $formattedMethod = $this->formatter->formatShippingMethod($method, $shippingCosts);

            # either assign to our "selected" method which needs to be shown
            # first in the apple pay list, or to the rest which is
            # then appended after our default selection.
            if ($method->getId() === $currentMethodID) {
                $selectedMethod = $formattedMethod;
            } else {
                $allMethods[] = $formattedMethod;
            }
        }

        $finalMethods = [];

        # our pre-selected method always needs
        # to be the first item in the list
        if ($selectedMethod !== null) {
            $finalMethods[] = $selectedMethod;
        }

        foreach ($allMethods as $method) {
            $finalMethods[] = $method;
        }

        return $finalMethods;
    }

    /**
     * @param ApplePayCart $cart
     * @param bool $isTestMode
     * @param SalesChannelContext $context
     * @return array
     */
    public function format(ApplePayCart $cart, bool $isTestMode, SalesChannelContext $context): array
    {
        return $this->formatter->formatCart($cart, $context->getSalesChannel(), $isTestMode);
    }

}
