<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\StateMachineState;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class StateMachineStateRepository implements StateMachineStateRepositoryInterface
{
    /**
     * @var EntityRepository<StateMachineStateCollection>
     */
    private $stateMachineStateRepository;

    /**
     * @param EntityRepository<StateMachineStateCollection> $stateMachineStateRepository
     */
    public function __construct(EntityRepository $stateMachineStateRepository)
    {
        $this->stateMachineStateRepository = $stateMachineStateRepository;
    }


    public function findByStateId(string $stateId, Context $context): ?StateMachineStateEntity
    {
        $criteria = new Criteria([$stateId]);
        $searchResult = $this->stateMachineStateRepository->search($criteria, $context);
        if ($searchResult->count() === 0) {
            return null;
        }
        /** @var StateMachineStateEntity $entity */
        $entity = $searchResult->first();

        return $entity;
    }
}
