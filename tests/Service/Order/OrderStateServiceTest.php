<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Tests\Service\Order;

use Exception;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Service\Order\OrderStateService;
use Kiener\MolliePayments\Setting\MollieSettingStruct;
use Kiener\MolliePayments\Tests\Fakes\FakeEntityRepository;
use Kiener\MolliePayments\Tests\Fakes\FakeOrderTransitionService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\LogEntryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

class OrderStateServiceTest extends TestCase
{
    /** @var FakeEntityRepository */
    private $loggerRepository;

    /** @var LoggerService */
    private $loggerService;

    /** @var FakeOrderTransitionService */
    private $orderTransitionService;

    /** @var OrderStateService */
    private $orderStateHelper;

    public function setUp(): void
    {
        $this->loggerRepository = new FakeEntityRepository(new LogEntryDefinition());
        $this->loggerService = new LoggerService($this->loggerRepository);
        $this->orderTransitionService = new FakeOrderTransitionService();
        $this->orderStateHelper = new OrderStateService($this->loggerService, $this->orderTransitionService);
    }

    /**
     * Test if nothing is done if the requested state is the skip state
     * which is the default if nothing is configured in the plugin config
     */
    public function testSetOrderStateDoesNothingOnSkipState(): void
    {
        $order = new OrderEntity();
        $order->setId('orderId');

        $context = $this->createMock(Context::class);

        $this->assertFalse($this->orderStateHelper->setOrderState($order, MollieSettingStruct::ORDER_STATE_SKIP, $context));
    }

    /**
     * Tests if nothing is done if the order is already in the correct state
     */
    public function testSetOrderStateDoesNothingIfOrderIsAlreadyInThisState(): void
    {
        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setTechnicalName(OrderStates::STATE_OPEN);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->assertFalse($this->orderStateHelper->setOrderState($order, OrderStates::STATE_OPEN, $context));
    }

    /**
     * Tests if nothing is done if the target state is invalid
     */
    public function testSetOrderStateDoesNothingOnInvalidState(): void
    {
        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setTechnicalName(OrderStates::STATE_OPEN);

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->assertFalse($this->orderStateHelper->setOrderState($order, 'invalid_state', $context));
    }

    /**
     * Tests if the expected methods of OrderTransitionHelper are called
     *
     * @dataProvider provideOrderState
     */
    public function testSetOrderStateCallsCorrectFunctionsAtOrderTransitionHelper(string $orderState): void
    {
        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setTechnicalName('current_state');

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $this->assertTrue($this->orderStateHelper->setOrderState($order, $orderState, $context));

        $this->assertEquals([$orderState], $this->orderTransitionService->states);
    }

    /**
     * Tests if log entry is written if an exception is thrown at
     * performing the transition
     */
    public function testSetOrderCreatesLogEntryOnException(): void
    {
        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setId('stateId');
        $stateMachineState->setTechnicalName('current_state');

        $order = new OrderEntity();
        $order->setId('orderId');
        $order->setStateMachineState($stateMachineState);

        $context = $this->createMock(Context::class);

        $exception = new Exception();
        $this->orderTransitionService->exception = $exception;

        $event = $this->createMock(EntityWrittenContainerEvent::class);
        $this->loggerRepository->entityWrittenContainerEvents = [$event];

        $this->assertFalse($this->orderStateHelper->setOrderState($order, OrderStates::STATE_OPEN, $context));

        $this->assertEquals(1, count($this->loggerRepository->data));
    }

    public function provideOrderState(): array
    {
        return [
            [OrderStates::STATE_OPEN],
            [OrderStates::STATE_IN_PROGRESS],
            [OrderStates::STATE_COMPLETED],
            [OrderStates::STATE_CANCELLED],
        ];
    }
}
