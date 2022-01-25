<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Facade\MollieShipment;
use Kiener\MolliePayments\Service\SettingsService;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDeliverySubscriber implements EventSubscriberInterface
{

    /**
     * @var SettingsService
     */
    private $settings;

    /**
     * @var MollieShipment
     */
    private $mollieShipment;

    /**
     * @param SettingsService $settings
     * @param MollieShipment $mollieShipment
     */
    public function __construct(SettingsService $settings, MollieShipment $mollieShipment)
    {
        $this->settings = $settings;
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

    /**
     * @param StateMachineStateChangeEvent $event
     */
    public function onOrderDeliveryChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $transitionName = $event->getTransition()->getTransitionName();

        if ($transitionName !== StateMachineTransitionActions::ACTION_SHIP) {
            return;
        }

        # get the configuration of the sales channel from the order
        $configSalesChannel = $this->settings->getSettings($event->getSalesChannelId());

        # if we don't even configure automatic shipping
        # then don't even look into our order to find out if we should actually starts
        if (!$configSalesChannel->getAutomaticShipping()) {
            return;
        }

        $isMolliePayment = $this->mollieShipment->isMolliePayment($event->getTransition()->getEntityId(), $event->getContext());

        # don't do anything for orders of other PSPs.
        # the code below would also create logs until we refactor it, which is wrong for other PSPs
        if (!$isMolliePayment) {
            return;
        }

        $this->mollieShipment->setShipment($event->getTransition()->getEntityId(), $event->getContext());
    }

}
