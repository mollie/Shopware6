<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Components\RefundManager\Service\OrderReturnHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderReturnSubscriber implements EventSubscriberInterface
{
    private const STATE_MACHINE_EVENT = 'state_machine.order_return.state_changed';
    private const STATE_DONE = 'done';
    private const STATE_CANCELLED = 'cancelled';

    private OrderReturnHandler $orderReturnHandler;

    public function __construct(OrderReturnHandler $orderReturnHandler)
    {
        $this->orderReturnHandler = $orderReturnHandler;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::STATE_MACHINE_EVENT => ['onOrderReturnStateChanged', 10],
            'order_return.written' => ['onOrderReturnWritten', 10],
        ];
    }

    public function onOrderReturnWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $result) {
            if ($result->getOperation() !== EntityWriteResult::OPERATION_INSERT) {
                continue;
            }
            $returnId = $result->getPrimaryKey();
            if (! is_string($returnId)) {
                continue;
            }
            $this->orderReturnHandler->returnOnCreatedAsDone($returnId, $event->getContext());
        }
    }

    public function onOrderReturnStateChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $returnId = $event->getTransition()->getEntityId();
        $context = $event->getContext();

        switch ($event->getStateName()) {
            case self::STATE_DONE:
                $this->orderReturnHandler->return($returnId, $context);
                break;
            case self::STATE_CANCELLED:
                $this->orderReturnHandler->cancel($returnId, $context);
                break;
        }
    }
}
