<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Cart\Subscription;

use Kiener\MolliePayments\Event\MollieSubscriptionCartItemAddedEvent;
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
     * @var SubscriptionProductIdentifier
     */
    private $subscriptionProductIdentifier;

    /**
     * SubscriptionCartCollector constructor.
     *
     * @param EventDispatcherInterface $dispatcher The event dispatcher
     * @param SubscriptionProductIdentifier $subscriptionProductIdentifier
     */
    public function __construct(EventDispatcherInterface $dispatcher, SubscriptionProductIdentifier $subscriptionProductIdentifier)
    {
        $this->dispatcher = $dispatcher;
        $this->subscriptionProductIdentifier = $subscriptionProductIdentifier;
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
            if ($this->subscriptionProductIdentifier->isSubscriptionProduct($lineItem)) {
                $events[] = new MollieSubscriptionCartItemAddedEvent($context, $lineItem);
            }
        }
        array_map([$this->dispatcher, 'dispatch'], $events);
    }
}
