<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Subscription\Cart;

use Mollie\Shopware\Component\Subscription\Event\SubscriptionLineItemAddedEvent;
use Mollie\Shopware\Entity\Product\Product;
use Mollie\Shopware\Mollie;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AutoconfigureTag('shopware.cart.collector', ['priority' => 1999])]
final class SubscriptionCartCollector implements CartDataCollectorInterface
{
    public function __construct(private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        foreach ($original->getLineItems() as $lineItem) {
            if ($this->isSubscriptionLineItem($lineItem)) {
                $this->eventDispatcher->dispatch(new SubscriptionLineItemAddedEvent($lineItem, $context));
            }
        }
    }

    private function isSubscriptionLineItem(LineItem $lineItem): bool
    {
        $extension = $lineItem->getExtension(Mollie::EXTENSION);

        return $extension instanceof Product && $extension->isSubscription() === true;
    }
}
