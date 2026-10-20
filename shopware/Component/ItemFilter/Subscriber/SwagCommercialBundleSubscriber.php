<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\ItemFilter\Subscriber;

use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SwagCommercialBundleSubscriber implements EventSubscriberInterface
{
    private const PAYLOAD_KEY = 'bundleDiscountId';

    public static function getSubscribedEvents(): array
    {
        return [
            FilterLineItemEvent::class => 'onFilterLineItem',
        ];
    }

    public function onFilterLineItem(FilterLineItemEvent $event): void
    {
        if ($event->getType() !== CartLineItem::DISCOUNT_LINE_ITEM) {
            return;
        }

        if (array_key_exists(self::PAYLOAD_KEY, $event->getPayload()) === false) {
            return;
        }

        $event->disallow();
    }
}
