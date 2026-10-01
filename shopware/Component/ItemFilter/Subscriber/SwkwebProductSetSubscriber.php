<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\ItemFilter\Subscriber;

use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class SwkwebProductSetSubscriber implements EventSubscriberInterface
{
    private const CONTAINER_TYPES = [
        'swkweb-product-set',
        'swkweb-product-set-slot',
        'swkweb-product-set-option',
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            FilterLineItemEvent::class => 'onFilterLineItem',
        ];
    }

    public function onFilterLineItem(FilterLineItemEvent $event): void
    {
        if (in_array($event->getType(), self::CONTAINER_TYPES, true) === false) {
            return;
        }

        $event->disallow();
    }
}
