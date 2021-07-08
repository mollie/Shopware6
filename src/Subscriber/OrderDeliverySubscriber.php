<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Facade\MollieShipment;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Throwable;

class OrderDeliverySubscriber implements EventSubscriberInterface
{
    /** @var MollieShipment */
    private $mollieShipment;

    public function __construct(
        MollieShipment $mollieShipment
    )
    {
        $this->mollieShipment = $mollieShipment;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order_delivery.state_changed' => 'onOrderDeliveryChanged',
        ];
    }

    public function onOrderDeliveryChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $transitionName = $event->getTransition()->getTransitionName();

        if ($transitionName !== StateMachineTransitionActions::ACTION_SHIP) {
            return;
        }

        $this->mollieShipment->setShipment($event->getTransition()->getEntityId(), $event->getContext());
    }
}
