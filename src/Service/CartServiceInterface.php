<?php

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CartServiceInterface
{
    /**
     * @param string $productId
     * @param int $quantity
     * @param SalesChannelContext $context
     * @return Cart
     */
    public function addProduct(string $productId, int $quantity, SalesChannelContext $context): Cart;

    /**
     * @param SalesChannelContext $salesChannelContext
     * @return Cart
     */
    public function getCalculatedMainCart(SalesChannelContext $salesChannelContext): Cart;

    /**
     * @param Cart $cart
     */
    public function updateCart(Cart $cart): void;

    /**
     * @param Cart $cart
     * @return float
     */
    public function getShippingCosts(Cart $cart): float;
    
    /**
     * @param SalesChannelContext $context
     * @param string $countryID
     * @return SalesChannelContext
     */
    public function updateCountry(SalesChannelContext $context, string $countryID): SalesChannelContext;

    /**
     * @param SalesChannelContext $context
     * @param string $shippingMethodID
     * @return SalesChannelContext
     */
    public function updateShippingMethod(SalesChannelContext $context, string $shippingMethodID): SalesChannelContext;

    /**
     * @param SalesChannelContext $context
     * @param string $paymentMethodID
     * @return SalesChannelContext
     */
    public function updatePaymentMethod(SalesChannelContext $context, string $paymentMethodID): SalesChannelContext;
}
