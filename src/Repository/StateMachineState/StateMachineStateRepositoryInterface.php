<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\StateMachineState;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

interface StateMachineStateRepositoryInterface
{
    public function findByStateId(string $stateId, Context $context): ?StateMachineStateEntity;
}
