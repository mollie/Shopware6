<?php
declare(strict_types=1);

namespace MolliePayments\Shopware\Tests\Traits;

use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\FlowState;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEvent;

trait FlowBuilderTestTrait
{
    protected function buildOrderStateFlowEvent(OrderEntity $order, string $actionName)
    {
        $context = Context::createDefaultContext();
        $event = new OrderStateMachineStateChangeEvent(
            'state_enter.order.state.in_progress',
            $order,
            $context
        );
        if (class_exists(FlowEvent::class)) {
            return new FlowEvent(
                $actionName,
                new FlowState($event),
                ['data' => 'not-empty']
            );
        }

        return new StorableFlow($actionName,$context,['orderId' => $order->getId()],['data' => 'not-empty']);
    }
}
