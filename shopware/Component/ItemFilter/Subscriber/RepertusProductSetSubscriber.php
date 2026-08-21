<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\ItemFilter\Subscriber;

use Mollie\Shopware\Component\Mollie\Event\FilterLineItemEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Repertus product sets: the container carries no price, the products of the set do.
 */
final class RepertusProductSetSubscriber implements EventSubscriberInterface
{
    private const CONTAINER_TYPE = 'repertus_product_container';

    public static function getSubscribedEvents(): array
    {
        return [
            FilterLineItemEvent::class => 'onFilterLineItem',
        ];
    }

    public function onFilterLineItem(FilterLineItemEvent $event): void
    {
        if ($event->getType() !== self::CONTAINER_TYPE) {
            return;
        }

        $event->disallow();
    }
}
