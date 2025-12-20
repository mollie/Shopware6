<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\StateHandler\Fake;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

final class FakeStateMachineRegistry extends StateMachineRegistry
{
    private array $actions = [];

    public function __construct(private FakeStateMachineRepository $repository)
    {
    }

    public function transition(Transition $transition, Context $context): StateMachineStateCollection
    {
        $this->actions[] = $transition->getTransitionName();
        $transition = $this->repository->findByActionName($transition->getTransitionName());
        $result = new StateMachineStateCollection();
        if ($transition instanceof StateMachineTransitionEntity) {
            $result->set('fromPlace',$transition->getFromStateMachineState());
            $result->set('toPlace',$transition->getToStateMachineState());
        }

        return $result;
    }

    public function getActions(): array
    {
        return $this->actions;
    }
}
