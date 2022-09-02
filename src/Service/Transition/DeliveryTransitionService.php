<?php

namespace Kiener\MolliePayments\Service\Transition;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;

class DeliveryTransitionService implements DeliveryTransitionServiceInterface
{
    /**
     * @var TransitionServiceInterface
     */
    private $transitionService;

    /**
     * @var LoggerInterface
     */
    private $loggerService;


    /**
     * @param TransitionServiceInterface $transitionService
     * @param LoggerInterface $loggerService
     */
    public function __construct(TransitionServiceInterface $transitionService, LoggerInterface $loggerService)
    {
        $this->transitionService = $transitionService;
        $this->loggerService = $loggerService;
    }

    /**
     * @param OrderDeliveryEntity $delivery
     * @param Context $context
     */
    public function reOpenDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);
        $statusName = $this->getStatusName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_OPEN) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_REOPEN, $availableTransitions)) {
            $this->loggerService->error(
                sprintf(
                    'It is not allowed to change status to open from %s. Aborting reopen transition',
                    $statusName
                )
            );

            return;
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
    }

    /**
     * @param OrderDeliveryEntity $delivery
     * @param Context $context
     */
    public function cancelDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_CANCELLED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_CANCEL, $availableTransitions)) {
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_CANCEL, $context);
    }

    /**
     * @param OrderDeliveryEntity $delivery
     * @param Context $context
     */
    public function shipDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_SHIPPED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_SHIP, $availableTransitions)) {
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_SHIP, $context);
    }

    /**
     * @param OrderDeliveryEntity $delivery
     * @param Context $context
     */
    public function partialShipDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_PARTIALLY_SHIPPED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_SHIP_PARTIALLY, $availableTransitions)) {
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_SHIP_PARTIALLY, $context);
    }

    /**
     * @param OrderDeliveryEntity $delivery
     * @param Context $context
     */
    public function returnDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_RETURNED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_RETOUR, $availableTransitions)) {
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_SHIP, $context);
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_RETOUR, $context);
    }

    /**
     * @param OrderDeliveryEntity $delivery
     * @param Context $context
     */
    public function partialReturnDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_PARTIALLY_RETURNED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (!$this->transitionIsAllowed(StateMachineTransitionActions::ACTION_RETOUR_PARTIALLY, $availableTransitions)) {
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_SHIP, $context);
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_RETOUR_PARTIALLY, $context);
    }

    /**
     * Gets the currently available transitions for the delivery entity
     *
     * @param OrderDeliveryEntity $delivery
     * @param Context $context
     * @return array<string>
     */
    private function getAvailableTransitions(OrderDeliveryEntity $delivery, Context $context): array
    {
        return $this->transitionService->getAvailableTransitions(OrderDeliveryDefinition::ENTITY_NAME, $delivery->getId(), $context);
    }

    /**
     * Performs the delivery transition
     *
     * @param OrderDeliveryEntity $delivery
     * @param string $transitionName
     * @param Context $context
     */
    private function performTransition(OrderDeliveryEntity $delivery, string $transitionName, Context $context): void
    {
        $this->transitionService->performTransition(OrderDeliveryDefinition::ENTITY_NAME, $delivery->getId(), $transitionName, $context);
    }

    /**
     * Checks if the requested transition is allowed for the current delivery state
     *
     * @param string $transition
     * @param array<mixed> $availableTransitions
     * @return bool
     */
    private function transitionIsAllowed(string $transition, array $availableTransitions): bool
    {
        return $this->transitionService->transitionIsAllowed($transition, $availableTransitions);
    }

    /**
     * @param OrderDeliveryEntity $delivery
     * @return string
     */
    private function getStatusName(OrderDeliveryEntity $delivery): string
    {
        if ($delivery->getStateMachineState() instanceof StateMachineStateEntity) {
            return $delivery->getStateMachineState()->getName();
        }

        return '';
    }

    /**
     * @param OrderDeliveryEntity $delivery
     * @return string
     */
    private function getStatusTechnicalName(OrderDeliveryEntity $delivery): string
    {
        if ($delivery->getStateMachineState() instanceof StateMachineStateEntity) {
            return $delivery->getStateMachineState()->getTechnicalName();
        }

        return '';
    }
}
