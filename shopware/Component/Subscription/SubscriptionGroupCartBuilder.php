<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService as SalesChannelCartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
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
            // the converter defaults to the admin-edit permissions, which include skipCartPersistence;
            // an empty set gives a persisted cart like in the storefront
            SalesChannelContextService::PERMISSIONS => [],
        ];

        if ($addresses !== null) {
            $overrideOptions[SalesChannelContextService::BILLING_ADDRESS_ID] = $addresses->getBillingAddressId();
            $overrideOptions[SalesChannelContextService::SHIPPING_ADDRESS_ID] = $addresses->getShippingAddressId();
        }

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context, $overrideOptions);

        // same token as the context, like in the storefront; the order route deletes the cart by the context token
        $cart = $this->cartService->createNew($salesChannelContext->getToken());

        $cartLineItems = [];
        foreach ($groupLineItems as $orderLineItem) {
            $productId = $orderLineItem->getReferencedId();
            if ($productId === null || $productId === '') {
                continue;
            }
            $cartLineItem = $this->lineItemFactoryRegistry->create([
                'id' => $productId,
                'referencedId' => $productId,
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'quantity' => $orderLineItem->getQuantity(),
            ], $salesChannelContext);

            // mirror the storefront "Subscribe" flow so the renewal cart keeps its subscription marker;
            // without it the payment method availability rule drops the Mollie method during renewal
            $cartLineItem->setId(Mollie::SUBSCRIPTION_LINE_ITEM_PREFIX . $productId);
            $cartLineItem->setPayloadValue(Mollie::SUBSCRIPTION_PAYLOAD_KEY, true);

            $cartLineItems[] = $cartLineItem;
        }

        if (count($cartLineItems) === 0) {
            return null;
        }

        $cart = $this->cartService->add($cart, $cartLineItems, $salesChannelContext);

        return new SubscriptionGroupCart($cart, $salesChannelContext);
    }
}
