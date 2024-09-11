<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Cart\Subscription;

use Kiener\MolliePayments\Event\MollieSubscriptionCartItemAddedEvent;
use Kiener\MolliePayments\Struct\LineItem\LineItemAttributes;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class SubscriptionCartCollector
 *
 * This class is responsible for collecting subscription products added to the cart
 * and dispatching corresponding events.
 */
class SubscriptionCartCollector implements CartDataCollectorInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $dispatcher;

    /**
     * SubscriptionCartCollector constructor.
     *
     * @param EventDispatcherInterface $dispatcher The event dispatcher
     */
    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * Collects subscription line items from the cart and dispatches events for each subscription product added.
     *
     * @param CartDataCollection $data The cart data collection
     * @param Cart $original The original cart
     * @param SalesChannelContext $context The sales channel context
     * @param CartBehavior $behavior The cart behavior
     */
    public function collect(CartDataCollection $data, Cart $original, SalesChannelContext $context, CartBehavior $behavior): void
    {
        $events = [];
        foreach ($original->getLineItems() as $lineItem) {
            if ((new LineItemAttributes($lineItem))->isSubscriptionProduct()) {
                $events[] = new MollieSubscriptionCartItemAddedEvent($context, $lineItem);
            }
        }
        array_map([$this->dispatcher, 'dispatch'], $events);
    }
}
