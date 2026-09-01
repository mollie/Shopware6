<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Subscriber;

use Mollie\Shopware\Component\Subscription\PriceDrift\SubscriptionPriceCheckFlagger;
use Mollie\Shopware\Component\Subscription\PriceDrift\SubscriptionPriceCheckFlaggerInterface;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Flags affected subscriptions as dirty when a shipping method price changes,
 * since shipping costs are part of the recurring subscription total.
 */
final class ShippingPriceChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: SubscriptionPriceCheckFlagger::class)]
        private readonly SubscriptionPriceCheckFlaggerInterface $flagger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ShippingMethodPriceDefinition::ENTITY_NAME . '.written' => 'onShippingPriceWritten',
        ];
    }

    public function onShippingPriceWritten(EntityWrittenEvent $event): void
    {
        $shippingMethodIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                continue;
            }
            $shippingMethodId = $writeResult->getPayload()['shippingMethodId'] ?? null;
            if (is_string($shippingMethodId) && $shippingMethodId !== '') {
                $shippingMethodIds[] = $shippingMethodId;
            }
        }

        $this->flagger->flagByShippingMethodIds($shippingMethodIds);
    }
}
