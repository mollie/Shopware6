<?php
declare(strict_types=1);

namespace Mollie\Integration\Data;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CartLineItemController;
use Shopware\Storefront\Controller\CheckoutController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;

trait CheckoutTestBehaviour
{
    use IntegrationTestBehaviour;
    use ProductTestBehaviour;
    use RequestTestBehaviour;

    public function addItemToCart(string $productNumber, SalesChannelContext $salesChannelContext, int $quantity = 1): Response
    {
        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);
        /** @var CartLineItemController $cartItemAddRoute */
        $cartLineItemController = $this->getContainer()->get(CartLineItemController::class);

        $product = $this->getProductByNumber($productNumber, $salesChannelContext->getContext());
        $request = $this->createStoreFrontRequest($salesChannelContext);

        $requestDataBag = new RequestDataBag();

        $lineItemDataBag = new RequestDataBag([
            'id' => $product->getId(),
            'referenceId' => $product->getId(),
            'referencedId' => $product->getId(), //this is required for shopware 6.5.5.2
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'quantity' => $quantity,
        ]);

        $requestDataBag->set('lineItems', [
            $product->getId() => $lineItemDataBag
        ]);

        return $cartLineItemController->addLineItems($cart, $requestDataBag, $request, $salesChannelContext);
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $options = [
            SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethod->getId(),
            SalesChannelContextService::CUSTOMER_ID => $salesChannelContext->getCustomer()->getId(),
        ];

        return $this->getSalesChannelContext($salesChannelContext->getSalesChannel(), $options);
    }

    public function startCheckout(SalesChannelContext $salesChannelContext): Response
    {
        $request = $this->createStoreFrontRequest($salesChannelContext);
        $checkoutController = $this->getContainer()->get(CheckoutController::class);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('tos', true);

        $response = $checkoutController->order($requestDataBag, $salesChannelContext, $request);
        /** @var FlashBag $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');
        $flashBagData = $flashBag->peekAll();
        $dangerErrors = $flashBagData['danger'] ?? [];
        $hasFlashes = count($dangerErrors) > 0;

        $this->assertFalse($hasFlashes, 'Create order has error messages ' . print_r($dangerErrors, true));

        return $response;
    }
}
