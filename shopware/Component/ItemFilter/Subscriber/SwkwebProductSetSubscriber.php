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
    ];

    private const TYPE_OPTION = 'swkweb-product-set-option';

    public static function getSubscribedEvents(): array
    {
        return [
            FilterLineItemEvent::class => 'onFilterLineItem',
        ];
    }

    public function onFilterLineItem(FilterLineItemEvent $event): void
    {
        $type = $event->getType();

        if (in_array($type, self::CONTAINER_TYPES, true)) {
            $event->disallow();

            return;
        }

        if ($type !== self::TYPE_OPTION || $event->hasChildren() === false) {
            return;
        }

        $event->disallow();
    }
}
