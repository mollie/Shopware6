<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Facade\MollieShipment;
use Kiener\MolliePayments\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
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
     * @var LoggerInterface
     */
    private $logger;


    /**
     * @param SettingsService $settings
     * @param MollieShipment $mollieShipment
     * @param LoggerInterface $logger
     */
    public function __construct(SettingsService $settings, MollieShipment $mollieShipment, LoggerInterface $logger)
    {
        $this->settings = $settings;
        $this->mollieShipment = $mollieShipment;
        $this->logger = $logger;
    }

    /**
     * @return array<mixed>
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

        /** @var ?OrderEntity $mollieOrder */
        $mollieOrder = $this->mollieShipment->isMollieOrder($event->getTransition()->getEntityId(), $event->getContext());

        # don't do anything for orders of other PSPs.
        # the code below would also create logs until we refactor it, which is wrong for other PSPs
        if (!$mollieOrder instanceof OrderEntity) {
            return;
        }
        
        $this->logger->info('Starting Shipment through Order Delivery Transition for order: ' . $mollieOrder->getOrderNumber());

        $this->mollieShipment->setShipment($event->getTransition()->getEntityId(), $event->getContext());
    }
}
