<?php

namespace Kiener\MolliePayments\Service;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as SalesChannelCartService;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartService
{
    /** @var SalesChannelCartService */
    private $salesChannelCartService;

    /**
     * Creates a new instance of the cart service.
     *
     * @param SalesChannelCartService $salesChannelCartService
     */
    public function __construct(
        SalesChannelCartService $salesChannelCartService
    )
    {
        $this->salesChannelCartService = $salesChannelCartService;
    }

    /**
     * Returns a cart by it's token.
     *
     * @param string              $cartToken
     * @param SalesChannelContext $salesChannelContext
     *
     * @return Cart
     */
    public function getCart(string $cartToken, SalesChannelContext $salesChannelContext): Cart
    {
        return $this->salesChannelCartService->getCart($cartToken, $salesChannelContext);
    }

    /**
     * Converts a cart to an order.
     *
     * @param Cart                $cart
     * @param SalesChannelContext $salesChannelContext
     *
     * @return string
     */
    public function order(Cart $cart, SalesChannelContext $salesChannelContext): string
    {
        return $this->salesChannelCartService->order($cart, $salesChannelContext);
    }

    /**
     * Returns a cart based on a new sales channel context.
     *
     * @param string               $productId
     * @param PaymentMethodEntity  $paymentMethod
     * @param ShippingMethodEntity $shippingMethod
     * @param SalesChannelContext  $salesChannelContext
     *
     * @return Cart
     */
    public function createCartForProduct(
        string $productId,
        PaymentMethodEntity $paymentMethod,
        ShippingMethodEntity $shippingMethod,
        SalesChannelContext $salesChannelContext
    ): Cart
    {
        /** @var SalesChannelContext $newSalesChannelContext */
        $newSalesChannelContext = new SalesChannelContext(
            $salesChannelContext->getContext(),
            $salesChannelContext->getToken(),
            $salesChannelContext->getSalesChannel(),
            $salesChannelContext->getCurrency(),
            $salesChannelContext->getCurrentCustomerGroup(),
            $salesChannelContext->getFallbackCustomerGroup(),
            $salesChannelContext->getTaxRules(),
            $paymentMethod,
            $shippingMethod,
            $salesChannelContext->getShippingLocation(),
            $salesChannelContext->getCustomer()
        );

        // Get a cart token
        $cartToken = Uuid::randomHex();

        // Create a cart
        $cart = new Cart('apple-pay-direct', $cartToken);

        /** @var LineItem $lineItem */
        $lineItem = new LineItem(
            $productId,
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            $productId,
            1
        );

        return $this->salesChannelCartService->add($cart, $lineItem, $newSalesChannelContext);
    }
}