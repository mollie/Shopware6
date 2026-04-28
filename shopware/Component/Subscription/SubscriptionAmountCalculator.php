<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as SalesChannelCartService;
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
        $orderLineItems = $order->getLineItems();
        if (! $orderLineItems instanceof OrderLineItemCollection) {
            return $order->getAmountTotal();
        }

        $groups = $this->lineItemAnalyzer->groupSubscriptionLineItemsByInterval($orderLineItems);
        $groupLineItems = $groups[$intervalKey] ?? [];

        if (count($groupLineItems) === 0) {
            return $order->getAmountTotal();
        }

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context, [
            SalesChannelContextService::PERMISSIONS => [
                CheckoutPermissions::SKIP_CART_PERSISTENCE => true,
            ],
        ]);

        $cartToken = Uuid::randomHex();
        $cart = $this->cartService->createNew($cartToken);

        $cartLineItems = [];
        foreach ($groupLineItems as $orderLineItem) {
            $productId = $orderLineItem->getProductId();
            if ($productId === null) {
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
            return $order->getAmountTotal();
        }

        $cart = $this->cartService->add($cart, $cartLineItems, $salesChannelContext);

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
}
