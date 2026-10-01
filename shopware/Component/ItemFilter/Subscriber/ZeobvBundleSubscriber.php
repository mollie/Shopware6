<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\ItemFilter\Subscriber;

use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ZeobvBundleSubscriber implements EventSubscriberInterface
{
    private const PAYLOAD_KEY = 'zeobvCustomLineItemType';
    private const BUNDLE_PRODUCT_ITEM = 'bundle_product_item';

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

        $bundleProductItemType = $event->getPayload()[self::PAYLOAD_KEY] ?? null;

        if ($bundleProductItemType !== self::BUNDLE_PRODUCT_ITEM) {
            return;
        }

        $event->disallow();
    }
}
