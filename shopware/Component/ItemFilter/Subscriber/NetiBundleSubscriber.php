<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\ItemFilter\Subscriber;

use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * NetInext bundles: the flagged bundle parent carries no price, the bundled products do.
 */
final class NetiBundleSubscriber implements EventSubscriberInterface
{
    private const PAYLOAD_KEY = 'is-neti-bundle';

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

        if (($event->getPayload()[self::PAYLOAD_KEY] ?? false) !== true) {
            return;
        }

        $event->disallow();
    }
}
