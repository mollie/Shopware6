<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface CartServiceInterface
{
    public function addProduct(string $productId, int $quantity, SalesChannelContext $context): Cart;

    public function getCalculatedMainCart(SalesChannelContext $salesChannelContext): Cart;

    public function updateCart(Cart $cart): void;

    public function getShippingCosts(Cart $cart): float;

    public function updateCountry(SalesChannelContext $context, string $countryID): SalesChannelContext;

    public function updateShippingMethod(SalesChannelContext $context, string $shippingMethodID): SalesChannelContext;

    public function updatePaymentMethod(SalesChannelContext $context, string $paymentMethodID): SalesChannelContext;

    public function persistCart(Cart $cart, SalesChannelContext $context): Cart;
}
