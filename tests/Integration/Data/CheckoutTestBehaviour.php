<?php
declare(strict_types=1);

namespace Mollie\Shopware\Integration\Data;

use Mollie\Shopware\Component\Payment\Controller\PaymentController;
use Mollie\Shopware\Mollie;
use PHPUnit\Framework\Assert;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\CartLineItemController;
use Shopware\Storefront\Controller\CheckoutController;
use Symfony\Component\HttpFoundation\Request;
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
            'referencedId' => $product->getId(), // this is required for shopware 6.5.5.2
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'quantity' => $quantity,
        ]);

        $requestDataBag->set('lineItems', [
            $product->getId() => $lineItemDataBag
        ]);

        return $cartLineItemController->addLineItems($cart, $requestDataBag, $request, $salesChannelContext);
    }

    /**
     * Adds the product as its subscription variant, i.e. the line item shaped exactly as the
     * SubscriptionCartItemAddRoute decorator produces it after a storefront "Subscribe" click:
     * a distinct line item id (so it does not merge with a one-off of the same product) plus the
     * subscription payload marker. A distinct id keeps the one-off and the subscription line
     * separate, and the marker makes LineItemSubscriber flag only this line as a subscription.
     */
    public function addSubscriptionItemToCart(string $productNumber, SalesChannelContext $salesChannelContext, int $quantity = 1): void
    {
        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $product = $this->getProductByNumber($productNumber, $salesChannelContext->getContext());

        $subscriptionLineItemId = Mollie::SUBSCRIPTION_LINE_ITEM_PREFIX . $product->getId();

        $lineItem = new LineItem($subscriptionLineItemId, LineItem::PRODUCT_LINE_ITEM_TYPE, $product->getId(), $quantity);
        $lineItem->setStackable(true);
        $lineItem->setRemovable(true);
        $lineItem->setPayloadValue(Mollie::SUBSCRIPTION_PAYLOAD_KEY, true);

        $cartService->add($cart, $lineItem, $salesChannelContext);
    }

    public function addPromotionToCart(string $code, SalesChannelContext $salesChannelContext): void
    {
        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $lineItem = (new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE))
            ->setReferencedId($code)
            ->setStackable(false)
            ->setRemovable(true)
        ;

        $cartService->add($cart, $lineItem, $salesChannelContext);
    }

    public function setPaymentMethod(PaymentMethodEntity $paymentMethod, SalesChannelContext $salesChannelContext): SalesChannelContext
    {
        $options = [
            SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethod->getId(),
            SalesChannelContextService::CUSTOMER_ID => $salesChannelContext->getCustomer()->getId(),
        ];

        return $this->getSalesChannelContext($salesChannelContext->getSalesChannel(), $options);
    }

    /**
     * @param array<string, string> $paymentData the payment specific fields the storefront form submits, such as the credit card token
     */
    public function startCheckout(SalesChannelContext $salesChannelContext, array $paymentData = []): Response
    {
        $request = $this->createStoreFrontRequest($salesChannelContext);
        $checkoutController = $this->getContainer()->get(CheckoutController::class);
        $requestDataBag = new RequestDataBag();
        $requestDataBag->set('tos', true);
        $requestDataBag->set('revocation', true);

        // Shopware 6.7 hands the payment handler the http request, 6.5 and 6.6 the data bag, so a
        // payment specific field has to be in both to arrive.
        foreach ($paymentData as $fieldName => $fieldValue) {
            $requestDataBag->set($fieldName, $fieldValue);
            $request->request->set($fieldName, $fieldValue);
        }

        /** @var FlashBag $flashBag */
        $flashBag = $request->getSession()->getBag('flashes');
        $flashBag->clear();

        $this->reloadCartFromStorage($salesChannelContext);

        $response = $checkoutController->order($requestDataBag, $salesChannelContext, $request);

        $flashBagData = $flashBag->peekAll();
        $dangerErrors = $flashBagData['danger'] ?? [];
        $warningErrors = $flashBagData['warning'] ?? [];
        $hasFlashes = count($dangerErrors) > 0 || count($warningErrors) > 0;

        if ($hasFlashes) {
            Assert::fail('Create order has error messages ' . print_r($dangerErrors + $warningErrors, true) . $this->describeCartState($salesChannelContext));
        }

        return $response;
    }

    public function findCurrencyByIso(string $currencyIso, SalesChannelContext $salesChannelContext): CurrencyEntity
    {
        /** @var EntityRepository $currencyRepository */
        $currencyRepository = $this->getContainer()->get('currency.repository');
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('isoCode', $currencyIso))
        ;

        return $currencyRepository->search($criteria, $salesChannelContext->getContext())->first();
    }

    public function finishCheckout(string $paymentUrl, SalesChannelContext $salesChannelContext): Response
    {
        $matches = [];
        preg_match('/mollie\/payment\/(?<paymentId>.*)/m', $paymentUrl, $matches);
        $paymentId = $matches['paymentId'] ?? null;

        if ($paymentId === null) {
            throw new \Exception('Failed to find Payment ID in ' . $paymentUrl);
        }

        /** @var PaymentController $returnController */
        $returnController = $this->getContainer()->get(PaymentController::class);

        return $returnController->return($paymentId, $salesChannelContext);
    }

    public function getOrderById(string $orderId, SalesChannelContext $salesChannelContext): OrderEntity
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get('order.repository');
        $criteria = (new Criteria([$orderId]));
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('deliveries.stateMachineState');
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));
        $criteria->addAssociation('lineItems.product');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.positions');
        $criteria->addAssociation('deliveries.shippingCosts');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');

        $searchResult = $repository->search($criteria, $salesChannelContext->getContext());

        return $searchResult->first();
    }

    private function reloadCartFromStorage(SalesChannelContext $salesChannelContext): void
    {
        $this->getContainer()->get(CartService::class)->getCart($salesChannelContext->getToken(), $salesChannelContext, false);
    }

    private function describeCartState(SalesChannelContext $salesChannelContext): string
    {
        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

        $calculatedCart = $this->getContainer()->get(CartCalculator::class)->calculate($cart, $salesChannelContext);

        $gatewayResponse = $this->getContainer()->get(CheckoutGatewayRoute::class)->load(new Request(), $cart, $salesChannelContext);

        $availablePaymentMethods = $gatewayResponse->getPaymentMethods()->map(
            static fn (PaymentMethodEntity $paymentMethod): string => (string) $paymentMethod->getTechnicalName()
        );
        $availableShippingMethods = $gatewayResponse->getShippingMethods()->map(
            static fn (ShippingMethodEntity $shippingMethod): string => (string) $shippingMethod->getTechnicalName()
        );

        return sprintf(
            "\ncached cart errors:       %s\ncalculated cart errors:   %s\ngateway errors:           %s\nselected payment method:  %s\nselected shipping method: %s\navailable payment methods: %s\navailable shipping methods: %s\n",
            implode(', ', $cart->getErrors()->getKeys()),
            implode(', ', $calculatedCart->getErrors()->getKeys()),
            implode(', ', $gatewayResponse->getErrors()->getKeys()),
            (string) $salesChannelContext->getPaymentMethod()->getTechnicalName(),
            (string) $salesChannelContext->getShippingMethod()->getTechnicalName(),
            implode(', ', $availablePaymentMethods),
            implode(', ', $availableShippingMethods)
        );
    }
}
