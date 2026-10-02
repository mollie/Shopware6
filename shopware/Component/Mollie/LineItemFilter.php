<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class LineItemFilter implements LineItemFilterInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        #[Autowire(service: 'monolog.logger.mollie')]
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Only Shopware's own delivery discount placeholder is decided here, everything a plugin adds
     * on top of plain products is decided by the listeners of FilterLineItemEvent.
     */
    public function isItemAllowed(CartLineItem|OrderLineItemEntity $item, ?OrderLineItemCollection $orderLineItems = null): bool
    {
        if ($item instanceof OrderLineItemEntity && LineItem::isDeliveryDiscountPlaceholder($item)) {
            return false;
        }

        $event = new FilterLineItemEvent($item, $this->hasChildren($item, $orderLineItems));

        try {
            $this->eventDispatcher->dispatch($event);
        } catch (\Throwable $exception) {
            // Breaks "do not swallow failures on a payment path" on purpose: the listeners are
            // foreign code and a broken one must not take down the checkout or the order view in
            // the administration. The decision of the listeners that did run still counts.
            $this->logger->error('A line item filter listener failed, the line item decision so far is kept', [
                'lineItemId' => $item->getId(),
                'lineItemType' => (string) $item->getType(),
                'error' => $exception->getMessage(),
            ]);
        }

        return $event->isAllowed();
    }

    private function hasChildren(CartLineItem|OrderLineItemEntity $item, ?OrderLineItemCollection $orderLineItems): bool
    {
        if ($item instanceof CartLineItem) {
            return $item->hasChildren();
        }

        $children = $item->getChildren();
        if ($children instanceof OrderLineItemCollection && $children->count() > 0) {
            return true;
        }

        if ($orderLineItems === null) {
            return false;
        }

        return $orderLineItems->filterByProperty('parentId', $item->getId())->count() > 0;
    }
}
