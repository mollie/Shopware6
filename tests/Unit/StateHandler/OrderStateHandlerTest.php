<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\StateHandler;

use Mollie\Shopware\Component\Settings\Struct\OrderStateSettings;
use Mollie\Shopware\Component\StateHandler\OrderStateHandler;
use Mollie\Shopware\Unit\Fake\CustomerEntityBuilder;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use Mollie\Shopware\Unit\StateHandler\Fake\FakeStateMachineRegistry;
use Mollie\Shopware\Unit\StateHandler\Fake\FakeStateMachineRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;

#[CoversClass(OrderStateHandler::class)]
final class OrderStateHandlerTest extends TestCase
{
    private Context $context;
    private FakeStateMachineRegistry $registry;

    public function setUp(): void
    {
        $this->context = new Context(new SystemSource());
    }

    public function testOrderStateIsFinal(): void
    {
        $fakeOrder = $this->getOrder();
        $fakeOrder->setStateId('finalId');

        $settings = new OrderStateSettings([], 'finalId');

        $stateHandler = $this->getOrderStateHandler($settings);

        $actual = $stateHandler->performTransition($fakeOrder, OrderTransactionStates::STATE_PAID, OrderStates::STATE_COMPLETED, 'test', $this->context);
        $this->assertSame('finalId', $actual);
    }

    public function testMappingForPaymentStateIsNotSet(): void
    {
        $fakeOrder = $this->getOrder();

        $stateHandler = $this->getOrderStateHandler();

        $actual = $stateHandler->performTransition($fakeOrder, OrderTransactionStates::STATE_PAID, OrderStates::STATE_OPEN, 'test', $this->context);
        $this->assertSame('openFakeStateId', $actual);
    }

    public function testOrderIsAlreadyInCurrentSettings(): void
    {
        $fakeOrder = $this->getOrder();

        $settings = new OrderStateSettings([
            OrderTransactionStates::STATE_PAID => 'open'
        ]);

        $stateHandler = $this->getOrderStateHandler($settings);

        $actual = $stateHandler->performTransition($fakeOrder, OrderTransactionStates::STATE_PAID, OrderStates::STATE_OPEN, 'test', $this->context);
        $this->assertSame('openFakeStateId', $actual);
    }

    public function testTransactionActionsAreNotSet(): void
    {
        $fakeOrder = $this->getOrder();

        $settings = new OrderStateSettings([
            OrderTransactionStates::STATE_PAID => OrderStates::STATE_COMPLETED
        ]);

        $stateHandler = $this->getOrderStateHandler($settings);

        $this->expectException(IllegalTransitionException::class);

        try {
            $stateHandler->performTransition($fakeOrder, OrderTransactionStates::STATE_PAID, OrderStates::STATE_OPEN, 'test', $this->context);
        } catch (IllegalTransitionException $exception) {
            $this->assertSame('SYSTEM__ILLEGAL_STATE_TRANSITION', $exception->getErrorCode());
            throw $exception;
        }
    }

    public function testTransactionIsMovedOnce(): void
    {
        $fakeOrder = $this->getOrder();

        $settings = new OrderStateSettings([
            OrderTransactionStates::STATE_PAID => OrderStates::STATE_IN_PROGRESS
        ]);
        $stateMachineRepository = new FakeStateMachineRepository();
        $stateMachineRepository->createDefaultCollection();

        $stateHandler = $this->getOrderStateHandler($settings, $stateMachineRepository);

        $actual = $stateHandler->performTransition($fakeOrder, OrderTransactionStates::STATE_PAID, OrderStates::STATE_OPEN, 'test', $this->context);

        $expectedTransition = ['process'];
        $this->assertSame('inProgressId', $actual);
        $this->assertSame($expectedTransition, $this->registry->getActions());
    }

    public function testTransactionIsMovedFromCancelledToComplete(): void
    {
        $fakeOrder = $this->getOrder();

        $settings = new OrderStateSettings([
            OrderTransactionStates::STATE_PAID => OrderStates::STATE_COMPLETED
        ]);
        $stateMachineRepository = new FakeStateMachineRepository();
        $stateMachineRepository->createDefaultCollection();

        $stateHandler = $this->getOrderStateHandler($settings, $stateMachineRepository);

        $actual = $stateHandler->performTransition($fakeOrder, OrderTransactionStates::STATE_PAID, OrderStates::STATE_CANCELLED, 'test', $this->context);

        $expectedTransition = ['reopen', 'process', 'complete'];
        $this->assertSame('completedId', $actual);
        $this->assertSame($expectedTransition, $this->registry->getActions());
    }

    public function testTransactionIsMovedFromCompleteToCancelled(): void
    {
        $fakeOrder = $this->getOrder();

        $settings = new OrderStateSettings([
            OrderTransactionStates::STATE_FAILED => OrderStates::STATE_CANCELLED
        ]);
        $stateMachineRepository = new FakeStateMachineRepository();
        $stateMachineRepository->createDefaultCollection();

        $stateHandler = $this->getOrderStateHandler($settings, $stateMachineRepository);

        $actual = $stateHandler->performTransition($fakeOrder, OrderTransactionStates::STATE_FAILED, OrderStates::STATE_COMPLETED, 'test', $this->context);

        $expectedTransition = ['reopen', 'cancel'];
        $this->assertSame('cancelledId', $actual);
        $this->assertSame($expectedTransition, $this->registry->getActions());
    }

    public function testInProgressToOpenIsNotReachableOnDefaultStateMachine(): void
    {
        $fakeOrder = $this->getOrder();

        $settings = new OrderStateSettings([
            OrderTransactionStates::STATE_AUTHORIZED => OrderStates::STATE_OPEN
        ]);
        $stateMachineRepository = new FakeStateMachineRepository();
        $stateMachineRepository->createDefaultCollection();

        $stateHandler = $this->getOrderStateHandler($settings, $stateMachineRepository);

        $this->expectException(IllegalTransitionException::class);

        try {
            $stateHandler->performTransition($fakeOrder, OrderTransactionStates::STATE_AUTHORIZED, OrderStates::STATE_IN_PROGRESS, 'test', $this->context);
        } catch (IllegalTransitionException $exception) {
            $this->assertSame([], $this->registry->getActions());
            $this->assertSame('SYSTEM__ILLEGAL_STATE_TRANSITION', $exception->getErrorCode());
            throw $exception;
        }
    }

    public function testInProgressToOpenIsNotReachableOnCustomerStateMachine(): void
    {
        $fakeOrder = $this->getOrder();

        $settings = new OrderStateSettings([
            OrderTransactionStates::STATE_AUTHORIZED => OrderStates::STATE_OPEN
        ]);
        $stateMachineRepository = new FakeStateMachineRepository();
        $stateMachineRepository->createCustomerCollection();

        $stateHandler = $this->getOrderStateHandler($settings, $stateMachineRepository);

        $this->expectException(IllegalTransitionException::class);

        try {
            $stateHandler->performTransition($fakeOrder, OrderTransactionStates::STATE_AUTHORIZED, OrderStates::STATE_IN_PROGRESS, 'test', $this->context);
        } catch (IllegalTransitionException $exception) {
            $this->assertSame([], $this->registry->getActions());
            $this->assertSame('SYSTEM__ILLEGAL_STATE_TRANSITION', $exception->getErrorCode());
            throw $exception;
        }
    }

    private function getOrder(): OrderEntity
    {
        $customerRepository = new CustomerEntityBuilder();
        $orderRepository = new OrderEntityBuilder();

        return $orderRepository->getDefaultOrder($customerRepository->getDefaultCustomer());
    }

    private function getOrderStateHandler(?OrderStateSettings $settings = null, ?FakeStateMachineRepository $stateMachineRepository = null): OrderStateHandler
    {
        if ($settings === null) {
            $settings = new OrderStateSettings([]);
        }
        if ($stateMachineRepository === null) {
            $stateMachineRepository = new FakeStateMachineRepository();
        }
        $this->registry = new FakeStateMachineRegistry($stateMachineRepository);

        return new OrderStateHandler(
            $stateMachineRepository,
            $this->registry,
            new FakeSettingsService(orderStateSettings: $settings),
            new NullLogger()
        );
    }
}
