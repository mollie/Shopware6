<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Components\ApplePayDirect\Services;

use Kiener\MolliePayments\Components\ApplePayDirect\Models\ApplePayCart;
use Kiener\MolliePayments\Service\CartService;
use Kiener\MolliePayments\Service\ShippingMethodService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ApplePayShippingBuilder
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

    public function __construct(CartService $cartService, ShippingMethodService $shippingMethodService, ApplePayFormatter $applePayFormatter)
    {
        $this->cartService = $cartService;
        $this->shippingMethods = $shippingMethodService;
        $this->formatter = $applePayFormatter;
    }

    /**
     * @return array<mixed>
     */
    public function getShippingMethods(string $countryID, SalesChannelContext $context): array
    {
        $currentMethodID = $context->getShippingMethod()->getId();

        // switch to the correct country of the apple pay user
        $context = $this->cartService->updateCountry($context, $countryID);

        $selectedMethod = null;
        $allMethods = [];

        $availableShippingMethods = $this->shippingMethods->getActiveShippingMethods($context);

        $this->cartService->clearFakeAddressIfExists($context);

        foreach ($availableShippingMethods as $method) {
            // temporary switch to our shipping method.
            // we will then load the cart for this shipping method
            // in order to get the calculated shipping costs for this.
            $tempContext = $this->cartService->updateShippingMethod($context, $method->getId());
            $tempCart = $this->cartService->getCalculatedMainCart($tempContext);

            $shippingCosts = $this->cartService->getShippingCosts($tempCart);

            // format it for apple pay
            $formattedMethod = $this->formatter->formatShippingMethod($method, $shippingCosts);

            // either assign to our "selected" method which needs to be shown
            // first in the apple pay list, or to the rest which is
            // then appended after our default selection.
            if ($method->getId() === $currentMethodID) {
                $selectedMethod = $formattedMethod;
            } else {
                $allMethods[] = $formattedMethod;
            }
        }

        $finalMethods = [];

        // our pre-selected method always needs
        // to be the first item in the list
        if ($selectedMethod !== null) {
            $finalMethods[] = $selectedMethod;
        }

        foreach ($allMethods as $method) {
            $finalMethods[] = $method;
        }

        return $finalMethods;
    }

    /**
     * @return array<mixed>
     */
    public function format(ApplePayCart $cart, bool $isTestMode, SalesChannelContext $context): array
    {
        return $this->formatter->formatCart($cart, $context->getSalesChannel(), $isTestMode);
    }
}
