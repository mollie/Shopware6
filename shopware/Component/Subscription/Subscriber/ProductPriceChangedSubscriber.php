<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Subscriber;

use Mollie\Shopware\Component\Subscription\PriceDrift\SubscriptionPriceCheckFlagger;
use Mollie\Shopware\Component\Subscription\PriceDrift\SubscriptionPriceCheckFlaggerInterface;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Flags affected subscriptions for a price re-check when a product price changes.
 *
 * Kept deliberately thin so product saves stay fast: it only collects the ids of
 * products whose price actually changed and hands them to the flagger. Whether a
 * product belongs to an active subscription is decided cheaply inside the flagger
 * via a join (no product entities are loaded here).
 */
final class ProductPriceChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SubscriptionPriceCheckFlagger::class)]
        private readonly SubscriptionPriceCheckFlaggerInterface $flagger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
            ProductEvents::PRODUCT_PRICE_WRITTEN_EVENT => 'onProductPriceWritten',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        $productIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                continue;
            }
            // Only react when the price was actually part of the write — not on
            // stock, name or other unrelated product updates.
            if (! array_key_exists('price', $writeResult->getPayload())) {
                continue;
            }
            $id = $writeResult->getPrimaryKey();
            if (is_string($id)) {
                $productIds[] = $id;
            }
        }

        $this->flagger->flagByProductIds($productIds);
    }

    public function onProductPriceWritten(EntityWrittenEvent $event): void
    {
        $productIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                continue;
            }
            // Advanced (tier) prices belong to a product via productId.
            $productId = $writeResult->getPayload()['productId'] ?? null;
            if (is_string($productId) && $productId !== '') {
                $productIds[] = $productId;
            }
        }

        $this->flagger->flagByProductIds($productIds);
    }
}
