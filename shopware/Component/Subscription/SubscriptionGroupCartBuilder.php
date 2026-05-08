<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as SalesChannelCartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionGroupCartBuilder implements SubscriptionGroupCartBuilderInterface
{
    public function __construct(
        #[Autowire(service: LineItemAnalyzer::class)]
        private readonly LineItemAnalyzerInterface $lineItemAnalyzer,
        private readonly OrderConverter $orderConverter,
        private readonly SalesChannelCartService $cartService,
        private readonly LineItemFactoryRegistry $lineItemFactoryRegistry,
    ) {
    }

    public function buildGroupCart(
        OrderEntity $order,
        string $intervalKey,
        Context $context,
        ?RenewalAddresses $addresses = null
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
                // CheckoutPermissions::SKIP_CART_PERSISTENCE does not exist in Shopware 6.5.x.
                // Once 6.5 support is dropped, replace this string with the class constant.
                'skipCartPersistence' => true,
            ],
        ];

        if ($addresses !== null) {
            $overrideOptions[SalesChannelContextService::BILLING_ADDRESS_ID] = $addresses->getBillingAddressId();
            $overrideOptions[SalesChannelContextService::SHIPPING_ADDRESS_ID] = $addresses->getShippingAddressId();
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
