<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service;

use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGateway;
use Kiener\MolliePayments\Compatibility\Gateway\CompatibilityGatewayInterface;
use Kiener\MolliePayments\Components\ApplePayDirect\Services\ApplePayShippingAddressFaker;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as SalesChannelCartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
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
     * @var ApplePayShippingAddressFaker
     */
    private $shippingAddressFaker;

    /**
     * @param CompatibilityGateway $compatibilityGateway
     */
    public function __construct(SalesChannelCartService $swCartService, SalesChannelContextSwitcher $contextSwitcher, ProductLineItemFactory $productItemFactory, CompatibilityGatewayInterface $compatibilityGateway, ApplePayShippingAddressFaker $shippingAddressFaker)
    {
        $this->swCartService = $swCartService;
        $this->contextSwitcher = $contextSwitcher;
        $this->productItemFactory = $productItemFactory;
        $this->compatibilityGateway = $compatibilityGateway;
        $this->shippingAddressFaker = $shippingAddressFaker;
    }

    public function addProduct(string $productId, int $quantity, SalesChannelContext $context): Cart
    {
        $cart = $this->getCalculatedMainCart($context);
        $data = [
            'id' => $productId,
            'referencedId' => $productId,
            'quantity' => $quantity,
        ];
        $productItem = $this->productItemFactory->create($data, $context);

        return $this->swCartService->add($cart, $productItem, $context);
    }

    public function getCalculatedMainCart(SalesChannelContext $salesChannelContext): Cart
    {
        $cart = $this->swCartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        return $this->swCartService->recalculate($cart, $salesChannelContext);
    }

    public function updateCart(Cart $cart): void
    {
        $this->swCartService->setCart($cart);
    }

    public function getShippingCosts(Cart $cart): float
    {
        return $cart->getDeliveries()->getShippingCosts()->sum()->getTotalPrice();
    }

    public function updateCountry(SalesChannelContext $context, string $countryID): SalesChannelContext
    {
        $dataBag = new DataBag();
        $dataBagData = [
            SalesChannelContextService::COUNTRY_ID => $countryID,
        ];
        $customer = $context->getCustomer();

        if ($customer instanceof CustomerEntity) {
            $applePayAddressId = $this->shippingAddressFaker->createFakeShippingAddress($countryID, $customer, $context->getContext());
            $dataBagData[SalesChannelContextService::SHIPPING_ADDRESS_ID] = $applePayAddressId;
        }

        $dataBag->add($dataBagData);

        $this->contextSwitcher->update($dataBag, $context);

        $scID = $this->compatibilityGateway->getSalesChannelID($context);
        $dID = $this->compatibilityGateway->getDomainId($context);

        return $this->compatibilityGateway->getSalesChannelContext($scID, $dID, $context->getToken());
    }

    public function updateShippingMethod(SalesChannelContext $context, string $shippingMethodID): SalesChannelContext
    {
        $dataBag = new DataBag();

        $dataBag->add([
            SalesChannelContextService::SHIPPING_METHOD_ID => $shippingMethodID,
        ]);

        $this->contextSwitcher->update($dataBag, $context);

        $scID = $this->compatibilityGateway->getSalesChannelID($context);
        $dID = $this->compatibilityGateway->getDomainId($context);

        return $this->compatibilityGateway->getSalesChannelContext($scID, $dID, $context->getToken());
    }

    public function updatePaymentMethod(SalesChannelContext $context, string $paymentMethodID): SalesChannelContext
    {
        $dataBag = new DataBag();

        $dataBag->add([
            SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodID,
        ]);

        $this->contextSwitcher->update($dataBag, $context);

        $scID = $this->compatibilityGateway->getSalesChannelID($context);
        $dID = $this->compatibilityGateway->getDomainId($context);

        return $this->compatibilityGateway->getSalesChannelContext($scID, $dID, $context->getToken());
    }

    public function clearFakeAddressIfExists(SalesChannelContext $context): void
    {
        $customer = $context->getCustomer();
        if ($customer === null) {
            return;
        }

        $this->shippingAddressFaker->deleteFakeShippingAddress($customer, $context->getContext());
    }

    public function persistCart(Cart $cart, SalesChannelContext $context): Cart
    {
        return $this->swCartService->recalculate($cart, $context);
    }
}
