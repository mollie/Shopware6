<?php

namespace MolliePayments\Tests\Traits;

use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;

trait FlowBuilderTestTrait
{
    /**
     * @param OrderEntity $order
     * @param string $actionName
     * @return FlowEvent
     */
    protected function buildOrderStateFlowEvent(OrderEntity $order, string $actionName): FlowEvent
    {
        $event = new OrderStateMachineStateChangeEvent(
            'state_enter.order.state.in_progress',
            $order,
            Context::createDefaultContext()
        );

        return new FlowEvent(
            $actionName,
            new FlowState($event),
            ['data' => 'not-empty']
        );
    }
}
