<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Facade\MollieShipment;
use Kiener\MolliePayments\Service\SettingsService;
use Mollie\Api\MollieApiClient;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDeliverySubscriber implements EventSubscriberInterface
{

    /** @var MollieApiClient */
    private $apiClient;

    /** @var SettingsService */
    protected $settingsService;

    /** @var MollieShipment */
    private $mollieShipment;

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     * * The method name to call (priority defaults to 0)
     * * An array composed of the method name to call and the priority
     * * An array of arrays composed of the method names to call and respective
     *   priorities, or 0 if unset
     *
     * For instance:
     *
     * * array('eventName' => 'methodName')
     * * array('eventName' => array('methodName', $priority))
     * * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order_delivery.state_changed' => 'onOrderDeliveryChanged',
        ];
    }

    /**
     * Creates a new instance of PaymentMethodSubscriber.
     *
     * @param MollieApiClient $apiClient
     * @param MollieShipment $mollieShipment
     */
    public function __construct(
        MollieApiClient $apiClient,
        MollieShipment $mollieShipment
    )
    {
        $this->apiClient = $apiClient;
        $this->mollieShipment = $mollieShipment;
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
