<?php

namespace Kiener\MolliePayments\Service;

use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as SalesChannelCartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartService
{
    /** @var SalesChannelCartService */
    private $salesChannelCartService;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        SalesChannelCartService $salesChannelCartService,
        ContainerInterface $container
    )
    {
        $this->salesChannelCartService = $salesChannelCartService;
        $this->container = $container;
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
        $customerId = '';
        $customer = $salesChannelContext->getCustomer();
        $billing = null;
        $shipping = null;

        if ($customer instanceof CustomerEntity) {
            $customerId = $customer->getId();
            $billing = $customer->getDefaultBillingAddress();
            $shipping = $customer->getDefaultShippingAddress();
        }

        $billingAddressId = '';

        if ($billing instanceof CustomerAddressEntity) {
            $billingAddressId = $billing->getId();
        }

        if ($shipping instanceof CustomerAddressEntity) {
            $shippingAddressId = $shipping->getId();
        } else {
            $shippingAddressId = $billingAddressId;
        }

        $languageIds = $salesChannelContext->getLanguageIdChain();

        $options = [
            SalesChannelContextService::CURRENCY_ID => $salesChannelContext->getCurrency()->getId(),
            SalesChannelContextService::LANGUAGE_ID => array_shift($languageIds),
            SalesChannelContextService::CUSTOMER_GROUP_ID => $salesChannelContext->getCurrentCustomerGroup()->getId(),
        ];

        if (!empty($customerId)) {
            $options[SalesChannelContextService::CUSTOMER_ID] = $customerId;
        }

        if (!empty($billingAddressId)) {
            $options[SalesChannelContextService::BILLING_ADDRESS_ID] = $billingAddressId;
        }

        if (!empty($shippingAddressId)) {
            $options[SalesChannelContextService::SHIPPING_ADDRESS_ID] = $shippingAddressId;
        }

        // because of backwards compatibility we have to get the factory on runtime via container. With 6.4 we would have to inject
        // the abstract version as dependency (the cached version could be injected).
        // The abstractFactory does not exist for sw versions < 6.4
        $salesChannelContextFactory = $this->container->get("Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory");

        $newSalesChannelContext = $salesChannelContextFactory->create(
            $salesChannelContext->getToken(),
            $salesChannelContext->getSalesChannel()->getId(),
            $options
        );

        $newSalesChannelContext->setRuleIds($salesChannelContext->getRuleIds());

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

        $lineItem->setStackable(true);

        return $this->salesChannelCartService->add($cart, $lineItem, $newSalesChannelContext);
    }
}
