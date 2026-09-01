<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\ItemFilter\Subscriber;

use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Mollie\Shopware\Component\Mollie\LineItemType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Shopware Custom Products: the container's product child carries the price, an option only
 * reaches Mollie when it adds a surcharge.
 */
final class CustomizedProductsSubscriber implements EventSubscriberInterface
{
    private const TYPE_OPTION = 'customized-products-option';

    public static function getSubscribedEvents(): array
    {
        return [
            FilterLineItemEvent::class => 'onFilterLineItem',
        ];
    }

    public function onFilterLineItem(FilterLineItemEvent $event): void
    {
        $type = $event->getType();

        if ($type === LineItemType::LINE_ITEM_TYPE_CUSTOM_PRODUCTS->value) {
            $event->disallow();

            return;
        }

        if ($type !== self::TYPE_OPTION) {
            return;
        }

        if ($event->getTotalPrice() > 0) {
            return;
        }

        $event->disallow();
    }
}
