<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\RefundManager\Service\OrderReturnHandler;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderReturnSubscriber implements EventSubscriberInterface
{
    private OrderReturnHandler $orderReturnHandler;

    public function __construct(OrderReturnHandler $orderReturnHandler)
    {
        $this->orderReturnHandler = $orderReturnHandler;
    }
    public static function getSubscribedEvents()
    {
        return [
            'state_enter.order_return.state.done' => ['onOrderReturnFinished', 10],
            'state_enter.order_return.state.cancelled' => ['onOrderReturnCancelled', 10],
        ];
    }


    public function onOrderReturnCancelled(OrderStateMachineStateChangeEvent $event): void
    {
        $this->orderReturnHandler->cancel($event->getOrder(), $event->getContext());
    }

    public function onOrderReturnFinished(OrderStateMachineStateChangeEvent $event): void
    {
        $this->orderReturnHandler->return($event->getOrder(), $event->getContext());
    }
}
