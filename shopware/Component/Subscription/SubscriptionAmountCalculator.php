<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as SalesChannelCartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionAmountCalculator implements SubscriptionAmountCalculatorInterface
{
    public function __construct(
        #[Autowire(service: LineItemAnalyzer::class)]
        private readonly LineItemAnalyzerInterface $lineItemAnalyzer,
        private readonly OrderConverter $orderConverter,
        private readonly SalesChannelCartService $cartService,
        private readonly LineItemFactoryRegistry $lineItemFactoryRegistry,
        #[Autowire(service: CartPersister::class)]
        private readonly AbstractCartPersister $cartPersister,
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

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context);

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
            $this->cartPersister->delete($cartToken, $salesChannelContext);

            return $order->getAmountTotal();
        }

        $cart = $this->cartService->add($cart, $cartLineItems, $salesChannelContext);

        $total = $cart->getPrice()->getTotalPrice();

        $this->cartPersister->delete($cartToken, $salesChannelContext);

        return $total;
    }
}
