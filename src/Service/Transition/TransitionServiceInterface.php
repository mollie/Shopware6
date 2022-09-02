<?php declare(strict_types=1);


namespace Kiener\MolliePayments\Service\Transition;

use Shopware\Core\Framework\Context;

interface TransitionServiceInterface
{
    /**
     * Checks if the requested transition is allowed for the current order state
     *
     * @param string $transition
     * @param array<mixed> $availableTransitions
     * @return bool
     */
    public function transitionIsAllowed(string $transition, array $availableTransitions): bool;

    /**
     * Gets the currently available transitions for the order entity
     *
     * @param string $definitionName
     * @param string $entityId
     * @param Context $context
     * @return array<string>
     */
    public function getAvailableTransitions(string $definitionName, string $entityId, Context $context): array;

    /**
     * Performs the order transition
     *
     * @param string $definitionName
     * @param string $entityId
     * @param string $transitionName
     * @param Context $context
     */
    public function performTransition(string $definitionName, string $entityId, string $transitionName, Context $context): void;
}
