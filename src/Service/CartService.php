<?php

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGateway;
use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as SalesChannelCartService;
use Shopware\Core\Content\Product\Cart\ProductLineItemFactory;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartService implements CartServiceInterface
{
    /**
     * @var SalesChannelCartService
     */
    private $swCartService;

    /**
     * @var SalesChannelContextSwitcher
     */
    private $contextSwitcher;

    /**
     * @var ProductLineItemFactory
     */
    private $productItemFactory;

    /**
     * @var CompatibilityGatewayInterface
     */
    private $compatibilityGateway;


    /**
     * @param SalesChannelCartService $swCartService
     * @param SalesChannelContextSwitcher $contextSwitcher
     * @param ProductLineItemFactory $productItemFactory
     * @param CompatibilityGateway $compatibilityGateway
     */
    public function __construct(SalesChannelCartService $swCartService, SalesChannelContextSwitcher $contextSwitcher, ProductLineItemFactory $productItemFactory, CompatibilityGatewayInterface $compatibilityGateway)
    {
        $this->swCartService = $swCartService;
        $this->contextSwitcher = $contextSwitcher;
        $this->productItemFactory = $productItemFactory;
        $this->compatibilityGateway = $compatibilityGateway;
    }


    /**
     * @param string $productId
     * @param int $quantity
     * @param SalesChannelContext $context
     * @return Cart
     */
    public function addProduct(string $productId, int $quantity, SalesChannelContext $context): Cart
    {
        $cart = $this->getCalculatedMainCart($context);

        $productItem = $this->productItemFactory->create($productId, ['quantity' => $quantity]);

        return $this->swCartService->add($cart, $productItem, $context);
    }

    /**
     * @param SalesChannelContext $salesChannelContext
     * @return Cart
     */
    public function getCalculatedMainCart(SalesChannelContext $salesChannelContext): Cart
    {
        $cart = $this->swCartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        return $this->swCartService->recalculate($cart, $salesChannelContext);
    }

    /**
     * @param Cart $cart
     */
    public function updateCart(Cart $cart): void
    {
        $this->swCartService->setCart($cart);
    }

    /**
     * @param Cart $cart
     * @return float
     */
    public function getShippingCosts(Cart $cart): float
    {
        return $cart->getDeliveries()->getShippingCosts()->sum()->getTotalPrice();
    }


    /**
     * @param SalesChannelContext $context
     * @param string $countryID
     * @return SalesChannelContext
     */
    public function updateCountry(SalesChannelContext $context, string $countryID): SalesChannelContext
    {
        $dataBag = new DataBag();

        $dataBag->add([
            SalesChannelContextService::COUNTRY_ID => $countryID
        ]);

        $this->contextSwitcher->update($dataBag, $context);

        $scID = $this->compatibilityGateway->getSalesChannelID($context);

        return $this->compatibilityGateway->getSalesChannelContext($scID, $context->getToken());
    }

    /**
     * @param SalesChannelContext $context
     * @param string $shippingMethodID
     * @return SalesChannelContext
     */
    public function updateShippingMethod(SalesChannelContext $context, string $shippingMethodID): SalesChannelContext
    {
        $dataBag = new DataBag();

        $dataBag->add([
            SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethodID
        ]);

        $this->contextSwitcher->update($dataBag, $context);

        $scID = $this->compatibilityGateway->getSalesChannelID($context);

        return $this->compatibilityGateway->getSalesChannelContext($scID, $context->getToken());
    }


    /**
     * @param SalesChannelContext $context
     * @param string $paymentMethodID
     * @return SalesChannelContext
     */
    public function updatePaymentMethod(SalesChannelContext $context, string $paymentMethodID): SalesChannelContext
    {
        $dataBag = new DataBag();

        $dataBag->add([
            SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodID
        ]);

        $this->contextSwitcher->update($dataBag, $context);

        $scID = $this->compatibilityGateway->getSalesChannelID($context);

        return $this->compatibilityGateway->getSalesChannelContext($scID, $context->getToken());
    }
}
