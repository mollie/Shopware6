<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\ItemFilter\Subscriber;

use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Zeobv product bundles: the bundle parent lists its products in the payload, and those products
 * are already in the flat list.
 */
final class ZeobvBundleSubscriber implements EventSubscriberInterface
{
    private const PAYLOAD_KEY = 'zeobvProductsInBundle';

    public static function getSubscribedEvents(): array
    {
        return [
            FilterLineItemEvent::class => 'onFilterLineItem',
        ];
    }

    public function onFilterLineItem(FilterLineItemEvent $event): void
    {
        if ($event->getType() !== CartLineItem::PRODUCT_LINE_ITEM_TYPE) {
            return;
        }

        $bundledProducts = $event->getPayload()[self::PAYLOAD_KEY] ?? [];
        if (is_array($bundledProducts) === false || count($bundledProducts) === 0) {
            return;
        }

        $event->disallow();
    }
}
