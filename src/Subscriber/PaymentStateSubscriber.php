<?php

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Exception\CouldNotSetRefundAtMollieException;
use Kiener\MolliePayments\Exception\MissingSalesChannelInOrderException;
use Kiener\MolliePayments\Facade\SetMollieOrderRefunded;
use Kiener\MolliePayments\Service\LoggerService;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentStateSubscriber implements EventSubscriberInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var SetMollieOrderRefunded
     */
    private $setMollieOrderRefunded;


    /**
     * @param SetMollieOrderRefunded $setMollieOrderRefunded
     * @param LoggerInterface $loggerService
     */
    public function __construct(SetMollieOrderRefunded $setMollieOrderRefunded, LoggerInterface $loggerService)
    {
        $this->setMollieOrderRefunded = $setMollieOrderRefunded;
        $this->logger = $loggerService;
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

            $this->logger->error(
                $e->getMessage()
            );
        }
    }
}
