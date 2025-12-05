<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\Event\CartLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class LineItemSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CartLoadedEvent::class => 'onCartLoaded',
            OrderEvents::ORDER_LINE_ITEM_LOADED_EVENT => 'onOrderLineItemLoaded',
        ];
    }

    public function onCartLoaded(CartLoadedEvent $event): void
    {
        $cart = $event->getCart();

        foreach ($cart->getLineItems() as $lineItem) {
            if ($lineItem->hasExtension(Mollie::EXTENSION)) {
                continue;
            }
            $customFields = $lineItem->getPayloadValue('customFields');
            if ($customFields === null) {
                continue;
            }
            Product::setFromCustomFields($lineItem, $customFields);
        }
    }

    /**
     * @param EntityLoadedEvent<OrderLineItemEntity> $event
     */
    public function onOrderLineItemLoaded(EntityLoadedEvent $event): void
    {
        /** @var OrderLineItemEntity $lineItem */
        foreach ($event->getEntities() as $lineItem) {
            if ($lineItem->hasExtension(Mollie::EXTENSION)) {
                continue;
            }
            $customFields = $lineItem->getPayload()['customFields'] ?? null;
            if ($customFields === null) {
                continue;
            }
            Product::setFromCustomFields($lineItem, $customFields);
        }
    }
}
