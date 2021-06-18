<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service\Transition;

use Kiener\MolliePayments\Service\Transition\TransitionService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class TransitionServiceTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|StateMachineRegistry
     */
    private $stateMachineRegistry;
    /**
     * @var TransitionService
     */
    private $transitionService;

    public function setUp(): void
    {
        $this->stateMachineRegistry = $this->getMockBuilder(StateMachineRegistry::class)->disableOriginalConstructor()->getMock();
        $this->transitionService = new TransitionService($this->stateMachineRegistry);
    }

    /**
     * Tests if the available transitions for the current state are returned
     */
    public function testGetAvailableTransitions(): void
    {
        $definitionName = OrderDefinition::ENTITY_NAME;

        $transition1 = new StateMachineTransitionEntity();
        $transition1->setId('transitionId1');
        $transition1->setActionName('transition_1');
        $transition2 = new StateMachineTransitionEntity();
        $transition2->setId('transitionId2');
        $transition2->setActionName('transition_2');

        $order = new OrderEntity();
        $orderId = Uuid::randomHex();
        $order->setId($orderId);

        $context = $this->createMock(Context::class);

        $this->stateMachineRegistry->expects($this->once())
            ->method('getAvailableTransitions')
            ->with($definitionName, $orderId, 'stateId', $context)
            ->willReturn([$transition1, $transition2]);

        $availableTransitions = $this->transitionService->getAvailableTransitions($definitionName, $orderId, $context);

        $expectedTransitions = ['transition_1', 'transition_2'];

        $this->assertEquals($expectedTransitions, $availableTransitions);
    }
}
