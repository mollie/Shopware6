<?php declare(strict_types=1);

namespace MolliePayments\Tests\Subscriber;

use Kiener\MolliePayments\Exception\MollieRefundException;
use Kiener\MolliePayments\Facade\SetMollieOrderRefunded;
use Kiener\MolliePayments\Service\LoggerService;
use Kiener\MolliePayments\Subscriber\PaymentStateSubscriber;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\Transition;

class PaymentStateSubscriberTest extends TestCase
{
    /**
     * @var SetMollieOrderRefunded|\PHPUnit\Framework\MockObject\MockObject
     */
    private $setMollieOrderRefunded;
    /**
     * @var LoggerService|\PHPUnit\Framework\MockObject\MockObject
     */
    private $loggerService;
    /**
     * @var PaymentStateSubscriber
     */
    private $subscriber;

    public function setUp(): void
    {
        $this->setMollieOrderRefunded = $this->getMockBuilder(SetMollieOrderRefunded::class)->disableOriginalConstructor()->getMock();
        $this->loggerService = $this->getMockBuilder(LoggerService::class)->disableOriginalConstructor()->getMock();
        $this->subscriber = new PaymentStateSubscriber($this->setMollieOrderRefunded, $this->loggerService);
    }

    public function testListener(): void
    {
        self::assertArrayHasKey('state_machine.order_transaction.state_changed', PaymentStateSubscriber::getSubscribedEvents());
    }

    public function testEventHasWrongTransitionSide()
    {
        $event = $this->getMockBuilder(StateMachineStateChangeEvent::class)->disableOriginalConstructor()->getMock();
        $event->method('getTransitionSide')->willReturn(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE);
        $event->expects($this->never())->method('getTransition');
        $this->setMollieOrderRefunded->expects($this->never())->method('setRefunded');
        $this->loggerService->expects($this->never())->method('addEntry');

        $this->subscriber->onOrderTransactionChanged($event);
    }

    public function testEventHasWrongTransitionAction()
    {
        $transition = new Transition('foo', 'bar', StateMachineTransitionActions::ACTION_FAIL, 'baz');
        $event = $this->getMockBuilder(StateMachineStateChangeEvent::class)->disableOriginalConstructor()->getMock();
        $event->method('getTransitionSide')->willReturn(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER);
        $event->expects($this->once())->method('getTransition')->willReturn($transition);
        $this->setMollieOrderRefunded->expects($this->never())->method('setRefunded');
        $this->loggerService->expects($this->never())->method('addEntry');

        $this->subscriber->onOrderTransactionChanged($event);
    }

    public function testFacadeWillBeCalledWithCorrectParameters()
    {
        $entityId = Uuid::randomHex();
        $transition = new Transition('foo', $entityId, StateMachineTransitionActions::ACTION_REFUND, 'bar');

        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();

        $event = $this->getMockBuilder(StateMachineStateChangeEvent::class)->disableOriginalConstructor()->getMock();
        $event->method('getTransitionSide')->willReturn(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER);
        $event->method('getContext')->willReturn($context);
        $event->method('getTransition')->willReturn($transition);

        $this->setMollieOrderRefunded->expects($this->once())->method('setRefunded')->with($entityId, $context);
        $this->loggerService->expects($this->never())->method('addEntry');

        $this->subscriber->onOrderTransactionChanged($event);
    }

    public function testFacadeWillBeCalledAndThrowsException()
    {
        $entityId = Uuid::randomHex();
        $transition = new Transition('foo', $entityId, StateMachineTransitionActions::ACTION_REFUND, 'bar');

        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();

        $event = $this->getMockBuilder(StateMachineStateChangeEvent::class)->disableOriginalConstructor()->getMock();
        $event->method('getTransitionSide')->willReturn(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER);
        $event->method('getContext')->willReturn($context);
        $event->method('getTransition')->willReturn($transition);

        $fooMessage = 'foo foo';
        $e = new MollieRefundException($fooMessage);
        $this->setMollieOrderRefunded->method('setRefunded')->willThrowException($e);

        $this->setMollieOrderRefunded->expects($this->once())->method('setRefunded')->with($entityId, $context);
        $this->loggerService->expects($this->once())->method('addEntry')->with(
            $fooMessage,
            $context,
            $e,
            [],
            Logger::ERROR
        );

        $this->subscriber->onOrderTransactionChanged($event);
    }
}
