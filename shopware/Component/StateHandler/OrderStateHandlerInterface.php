<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StateHandler;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

interface OrderStateHandlerInterface
{
    public function performTransition(OrderEntity $shopwareOrder, StateMachineStateEntity $currentOrderState,string $targetState, Context $context): string;
}
