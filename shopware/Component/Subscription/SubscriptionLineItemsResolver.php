<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription;

use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SubscriptionLineItemsResolver implements SubscriptionLineItemsResolverInterface
{
    /**
     * @param EntityRepository<EntityCollection<OrderEntity>> $orderRepository
     */
    public function __construct(
        private readonly CartService $cartService,
        #[Autowire(service: 'order.repository')]
        private readonly EntityRepository $orderRepository,
    ) {
    }

    public function resolveLineItems(string $orderId, SalesChannelContext $salesChannelContext): LineItemCollection|OrderLineItemCollection
    {
        if (mb_strlen($orderId) === 0) {
            $cart = $this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext);

            return new LineItemCollection($cart->getLineItems()->getFlat());
        }

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');

        $orderEntity = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();
        if (! $orderEntity instanceof OrderEntity) {
            return new LineItemCollection();
        }

        $lineItems = $orderEntity->getLineItems();
        if ($lineItems === null) {
            return new LineItemCollection();
        }

        return $lineItems;
    }
}
