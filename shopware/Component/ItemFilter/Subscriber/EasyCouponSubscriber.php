<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\ItemFilter\Subscriber;

use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class EasyCouponSubscriber implements EventSubscriberInterface
{
    private const EXTRA_OPTION_TYPES = [
        'easy-coupon-extra-option',
        'easy-coupon-extra-option-postal',
        'easy-coupon-extra-option-voucher',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            FilterLineItemEvent::class => 'onFilterLineItem',
        ];
    }

    public function onFilterLineItem(FilterLineItemEvent $event): void
    {
        if (\in_array($event->getType(), self::EXTRA_OPTION_TYPES, true) === false) {
            return;
        }

        $event->disallow();
    }
}
