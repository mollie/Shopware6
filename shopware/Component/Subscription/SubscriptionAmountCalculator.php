<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as SalesChannelCartService;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionAmountCalculator implements SubscriptionAmountCalculatorInterface
{
    public function __construct(
        #[Autowire(service: LineItemAnalyzer::class)]
        private readonly LineItemAnalyzerInterface $lineItemAnalyzer,
        private readonly OrderConverter $orderConverter,
        private readonly SalesChannelCartService $cartService,
        private readonly LineItemFactoryRegistry $lineItemFactoryRegistry,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger,
    ) {
    }

    public function calculateGroupAmount(OrderEntity $order, string $intervalKey, Context $context): float
    {
        $groupCart = $this->buildGroupCart($order, $intervalKey, $context);
        if ($groupCart === null) {
            return $order->getAmountTotal();
        }

        $cart = $groupCart->getCart();

        // CartPrice::getTotalPrice() is always the gross amount the customer pays — even for
        // TAX_STATE_FREE carts, where AmountCalculator::calculateNetDeliveryAmount sets
        // netPrice and totalPrice to the same value. Shopware itself relies on this: the
        // order.amount_total column is a VIRTUAL generated column extracting price.totalPrice
        // from the persisted CartPrice JSON (see Migration1536232990Order). No tax-state
        // branching needed here.
        $total = $cart->getPrice()->getTotalPrice();

        $this->logger->info('Subscription group amount calculated', [
            'orderNumber' => (string) $order->getOrderNumber(),
            'intervalKey' => $intervalKey,
            'cartTotal' => $cart->getPrice()->getTotalPrice(),
            'cartNetPrice' => $cart->getPrice()->getNetPrice(),
            'cartPositionPrice' => $cart->getPrice()->getPositionPrice(),
            'cartTaxStatus' => $cart->getPrice()->getTaxStatus(),
            'cartShippingCosts' => $cart->getDeliveries()->getShippingCosts()->sum()->getTotalPrice(),
            'amount' => $total,
        ]);

        return $total;
    }

    public function buildGroupCart(
        OrderEntity $order,
        string $intervalKey,
        Context $context,
        ?string $billingAddressId = null,
        ?string $shippingAddressId = null
    ): ?SubscriptionGroupCart {
        $orderLineItems = $order->getLineItems();
        if (! $orderLineItems instanceof OrderLineItemCollection) {
            return null;
        }

        $groups = $this->lineItemAnalyzer->groupSubscriptionLineItemsByInterval($orderLineItems);
        $groupLineItems = $groups[$intervalKey] ?? [];

        if (count($groupLineItems) === 0) {
            return null;
        }

        $overrideOptions = [
            SalesChannelContextService::PERMISSIONS => [
                CheckoutPermissions::SKIP_CART_PERSISTENCE => true,
            ],
        ];

        if ($billingAddressId !== null) {
            $overrideOptions[SalesChannelContextService::BILLING_ADDRESS_ID] = $billingAddressId;
        }
        if ($shippingAddressId !== null) {
            $overrideOptions[SalesChannelContextService::SHIPPING_ADDRESS_ID] = $shippingAddressId;
        }

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context, $overrideOptions);

        $cartToken = Uuid::randomHex();
        $cart = $this->cartService->createNew($cartToken);

        $cartLineItems = [];
        foreach ($groupLineItems as $orderLineItem) {
            $productId = $orderLineItem->getReferencedId();
            if ($productId === null || $productId === '') {
                continue;
            }
            $cartLineItems[] = $this->lineItemFactoryRegistry->create([
                'id' => $productId,
                'referencedId' => $productId,
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'quantity' => $orderLineItem->getQuantity(),
            ], $salesChannelContext);
        }

        if (count($cartLineItems) === 0) {
            return null;
        }

        $cart = $this->cartService->add($cart, $cartLineItems, $salesChannelContext);

        return new SubscriptionGroupCart($cart, $salesChannelContext);
    }
}
