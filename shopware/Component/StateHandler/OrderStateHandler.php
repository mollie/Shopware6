<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\StateHandler;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class OrderStateHandler implements OrderStateHandlerInterface
{
    public function __construct(
        private readonly StateMachineRegistry $stateMachineRegistry,
        #[Autowire(service: 'monolog.logger.mollie')]
        private LoggerInterface $logger
    ) {
    }

    public function performTransition(OrderEntity $shopwareOrder, StateMachineStateEntity $currentOrderState, string $targetState, Context $context): string
    {
        $orderNumber = $shopwareOrder->getOrderNumber();
        $salesChannelId = $shopwareOrder->getSalesChannelId();
        $orderId = $shopwareOrder->getId();
        $currentState = $currentOrderState->getTechnicalName();
        $logData = [
            'orderId' => $shopwareOrder->getId(),
            'salesChannelId' => $salesChannelId,
            'orderNumber' => $orderNumber,
            'currentState' => $currentState,
            'targetState' => $targetState,
        ];

        if ($targetState === $currentState) {
            $this->logger->debug('Order is already in current status', $logData);

            return $targetState;
        }
        $result = $this->stateMachineRegistry->getAvailableTransitions(OrderDefinition::ENTITY_NAME, $orderId, 'stateId', $context);
        $result2 = $this->stateMachineRegistry->getStateMachine(OrderStates::STATE_MACHINE, $context);
        $result3 = $this->stateMachineRegistry->transition(new Transition(
            OrderDefinition::ENTITY_NAME,
            $orderId,
            $targetState,
            'stateId'
        ),$context);

        // TODO: Implement performTransition() method.
        return '';
    }
}
