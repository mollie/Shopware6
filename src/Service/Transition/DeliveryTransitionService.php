<?php
declare(strict_types=1);

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

    public function __construct(TransitionServiceInterface $transitionService, LoggerInterface $loggerService)
    {
        $this->transitionService = $transitionService;
        $this->loggerService = $loggerService;
    }

    public function reOpenDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);
        $statusName = $this->getStatusName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_OPEN) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (! $this->transitionIsAllowed(StateMachineTransitionActions::ACTION_REOPEN, $availableTransitions)) {
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

    public function cancelDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_CANCELLED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (! $this->transitionIsAllowed(StateMachineTransitionActions::ACTION_CANCEL, $availableTransitions)) {
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_CANCEL, $context);
    }

    public function shipDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_SHIPPED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (! $this->transitionIsAllowed(StateMachineTransitionActions::ACTION_SHIP, $availableTransitions)) {
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_SHIP, $context);
    }

    public function partialShipDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_PARTIALLY_SHIPPED || $statusTechnical === OrderDeliveryStates::STATE_PARTIALLY_RETURNED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (! $this->transitionIsAllowed(StateMachineTransitionActions::ACTION_SHIP_PARTIALLY, $availableTransitions)) {
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_SHIP_PARTIALLY, $context);
    }

    public function returnDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_RETURNED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (! $this->transitionIsAllowed(StateMachineTransitionActions::ACTION_RETOUR, $availableTransitions)) {
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_SHIP, $context);
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_RETOUR, $context);
    }

    public function partialReturnDelivery(OrderDeliveryEntity $delivery, Context $context): void
    {
        $statusTechnical = $this->getStatusTechnicalName($delivery);

        if ($statusTechnical === OrderDeliveryStates::STATE_PARTIALLY_RETURNED) {
            return;
        }

        $availableTransitions = $this->getAvailableTransitions($delivery, $context);

        if (! $this->transitionIsAllowed(StateMachineTransitionActions::ACTION_RETOUR_PARTIALLY, $availableTransitions)) {
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_REOPEN, $context);
            $this->performTransition($delivery, StateMachineTransitionActions::ACTION_SHIP, $context);
        }

        $this->performTransition($delivery, StateMachineTransitionActions::ACTION_RETOUR_PARTIALLY, $context);
    }

    /**
     * Gets the currently available transitions for the delivery entity
     *
     * @return array<string>
     */
    private function getAvailableTransitions(OrderDeliveryEntity $delivery, Context $context): array
    {
        return $this->transitionService->getAvailableTransitions(OrderDeliveryDefinition::ENTITY_NAME, $delivery->getId(), $context);
    }

    /**
     * Performs the delivery transition
     */
    private function performTransition(OrderDeliveryEntity $delivery, string $transitionName, Context $context): void
    {
        $this->loggerService->debug(
            sprintf(
                'Performing transition %s for delivery %s',
                $transitionName,
                $delivery->getId()
            )
        );

        try {
            $this->transitionService->performTransition(OrderDeliveryDefinition::ENTITY_NAME, $delivery->getId(), $transitionName, $context);
        } catch (\Throwable $e) {
            $this->loggerService->error(
                $e->getMessage(),
                [
                    'method' => 'delivery-transition-perform-transition',
                    'delivery.id' => $delivery->getId(),
                    'delivery.payload' => $delivery->jsonSerialize(),
                    'transition' => $transitionName,
                ]
            );
        }
    }

    /**
     * Checks if the requested transition is allowed for the current delivery state
     *
     * @param array<mixed> $availableTransitions
     */
    private function transitionIsAllowed(string $transition, array $availableTransitions): bool
    {
        return $this->transitionService->transitionIsAllowed($transition, $availableTransitions);
    }

    private function getStatusName(OrderDeliveryEntity $delivery): string
    {
        $stateMachineState = $delivery->getStateMachineState();
        if ($stateMachineState instanceof StateMachineStateEntity) {
            return (string) $stateMachineState->getName();
        }

        return '';
    }

    private function getStatusTechnicalName(OrderDeliveryEntity $delivery): string
    {
        $stateMachineState = $delivery->getStateMachineState();
        if ($stateMachineState instanceof StateMachineStateEntity) {
            return (string) $stateMachineState->getTechnicalName();
        }

        return '';
    }
}
