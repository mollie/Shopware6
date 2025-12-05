<?php
declare(strict_types=1);

namespace Mollie\Shopware\Subscriber;

use Mollie\Shopware\Entity\Product\Product;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ProductSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_LOADED_EVENT => 'onProductLoaded',
        ];
    }

    /**
     * @param EntityLoadedEvent<ProductEntity> $event
     * @return void
     */
    public function onProductLoaded(EntityLoadedEvent $event): void
    {
        /** @var ProductEntity $product */
        foreach ($event->getEntities() as $product) {
            $customFields = $product->getCustomFields();
            if ($customFields === null) {
                continue;
            }
            Product::setFromCustomFields($product, $customFields);
        }
    }
}
