<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\ItemFilter\Subscriber;

use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem as CartLineItem;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * NetiNextEasyCoupon: the voucher product carries no price, its voucher and fee children do.
 */
final class EasyCouponSubscriber implements EventSubscriberInterface
{
    private const PAYLOAD_KEY = 'netiNextEasyCoupon';

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

        if (isset($event->getPayload()[self::PAYLOAD_KEY]) === false) {
            return;
        }

        $event->disallow();
    }
}
