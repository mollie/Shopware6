<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\StateHandler\Fake;

use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineEntity;

final class FakeStateMachineRepository extends EntityRepository
{
    public function __construct(private ?StateMachineTransitionCollection $collection = null)
    {
        if ($collection === null) {
            $this->collection = new StateMachineTransitionCollection();
        }
    }

    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return new EntitySearchResult(StateMachineTransitionEntity::class, $this->collection->count(), $this->collection, null, $criteria, $context);
    }

    public function findByActionName(string $actioName): ?StateMachineTransitionEntity
    {
        foreach ($this->collection as $stateMachineTransition) {
            if ($stateMachineTransition->getActionName() === $actioName) {
                return $stateMachineTransition;
            }
        }

        return null;
    }

    public function createDefaultCollection(): void
    {
        $collection = new StateMachineTransitionCollection();

        $stateMachine = new StateMachineEntity();
        $stateMachine->setId('oder.state.id');
        $stateMachine->setTechnicalName(OrderStates::STATE_MACHINE);

        $openState = new StateMachineStateEntity();
        $openState->setTechnicalName(OrderStates::STATE_OPEN);
        $openState->setName('Open');
        $openState->setId('openId');

        $inProgressState = new StateMachineStateEntity();
        $inProgressState->setTechnicalName(OrderStates::STATE_IN_PROGRESS);
        $inProgressState->setName('In Progress');
        $inProgressState->setId('inProgressId');

        $cancelledState = new StateMachineStateEntity();
        $cancelledState->setTechnicalName(OrderStates::STATE_CANCELLED);
        $cancelledState->setName('Cancelled');
        $cancelledState->setId('cancelledId');

        $completedState = new StateMachineStateEntity();
        $completedState->setTechnicalName(OrderStates::STATE_COMPLETED);
        $completedState->setName('Done');
        $completedState->setId('completedId');

        $transition = new StateMachineTransitionEntity();
        $transition->setId('processId');
        $transition->setActionName('process');
        $transition->setFromStateMachineState($openState);
        $transition->setFromStateId($openState->getId());
        $transition->setToStateMachineState($inProgressState);
        $transition->setToStateId($inProgressState->getId());
        $transition->setStateMachine($stateMachine);
        $collection->add($transition);

        $transition = new StateMachineTransitionEntity();
        $transition->setId('cancelFromOpenId');
        $transition->setActionName('cancel');
        $transition->setFromStateMachineState($openState);
        $transition->setFromStateId($openState->getId());
        $transition->setToStateMachineState($cancelledState);
        $transition->setToStateId($cancelledState->getId());
        $transition->setStateMachine($stateMachine);
        $collection->add($transition);

        $transition = new StateMachineTransitionEntity();
        $transition->setId('cancelFromInProgressId');
        $transition->setActionName('cancel');
        $transition->setFromStateMachineState($inProgressState);
        $transition->setFromStateId($inProgressState->getId());
        $transition->setToStateMachineState($cancelledState);
        $transition->setToStateId($cancelledState->getId());
        $transition->setStateMachine($stateMachine);
        $collection->add($transition);

        $transition = new StateMachineTransitionEntity();
        $transition->setId('doneId');
        $transition->setActionName('complete');
        $transition->setFromStateMachineState($inProgressState);
        $transition->setFromStateId($inProgressState->getId());
        $transition->setToStateMachineState($completedState);
        $transition->setToStateId($completedState->getId());
        $transition->setStateMachine($stateMachine);
        $collection->add($transition);

        $transition = new StateMachineTransitionEntity();
        $transition->setId('reopenFromCancelledId');
        $transition->setActionName('reopen');
        $transition->setFromStateMachineState($cancelledState);
        $transition->setFromStateId($cancelledState->getId());
        $transition->setToStateMachineState($openState);
        $transition->setToStateId($openState->getId());
        $transition->setStateMachine($stateMachine);
        $collection->add($transition);

        $transition = new StateMachineTransitionEntity();
        $transition->setId('reopenFromCompletedId');
        $transition->setActionName('reopen');
        $transition->setFromStateMachineState($completedState);
        $transition->setFromStateId($completedState->getId());
        $transition->setToStateMachineState($openState);
        $transition->setToStateId($openState->getId());
        $transition->setStateMachine($stateMachine);
        $collection->add($transition);

        $this->collection = $collection;
    }
}
