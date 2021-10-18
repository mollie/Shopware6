<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Exception\CouldNotSetRefundAtMollieException;
use Kiener\MolliePayments\Exception\MissingSalesChannelInOrderException;
use Kiener\MolliePayments\Facade\SetMollieOrderRefunded;
use Kiener\MolliePayments\Service\LoggerService;
use Monolog\Logger;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentStateSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerService
     */
    private $loggerService;
    /**
     * @var SetMollieOrderRefunded
     */
    private $setMollieOrderRefunded;

    /**
     * Creates a new instance of PaymentMethodSubscriber.
     *
     * @param SetMollieOrderRefunded $setMollieOrderRefunded
     * @param LoggerService $loggerService
     */
    public function __construct(
        SetMollieOrderRefunded $setMollieOrderRefunded,
        LoggerService $loggerService
    )
    {
        $this->setMollieOrderRefunded = $setMollieOrderRefunded;
        $this->loggerService = $loggerService;
    }

    public static function getSubscribedEvents()
    {
        return [
            'state_machine.order_transaction.state_changed' => 'onOrderTransactionChanged',
        ];
    }


    public function onOrderTransactionChanged(StateMachineStateChangeEvent $event)
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $transitionName = $event->getTransition()->getTransitionName();

        if ($transitionName != StateMachineTransitionActions::ACTION_REFUND) {
            return;
        }

        try {
            $this->setMollieOrderRefunded->setRefunded($event->getTransition()->getEntityId(), $event->getContext());
        } catch (CouldNotSetRefundAtMollieException | MissingSalesChannelInOrderException $e) {
            $this->loggerService->addEntry(
                $e->getMessage(),
                $event->getContext(),
                $e,
                [],
                Logger::ERROR
            );
        }
    }
}
