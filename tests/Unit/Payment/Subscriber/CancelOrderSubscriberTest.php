<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Subscriber;

use Mollie\Shopware\Component\Mollie\Exception\ApiException;
use Mollie\Shopware\Component\Mollie\Payment;
use Mollie\Shopware\Component\Payment\Subscriber\CancelOrderSubscriber;
use Mollie\Shopware\Component\Settings\Struct\PaymentSettings;
use Mollie\Shopware\Unit\Fake\FakeLogger;
use Mollie\Shopware\Unit\Fake\FakeOrderSearchRepository;
use Mollie\Shopware\Unit\Fake\FakeSettingsService;
use Mollie\Shopware\Unit\Fake\OrderEntityBuilder;
use Mollie\Shopware\Unit\Payment\Fake\FakeGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\StateMachineEntity;
use Shopware\Core\System\StateMachine\Transition;

#[CoversClass(CancelOrderSubscriber::class)]
final class CancelOrderSubscriberTest extends TestCase
{
    private FakeOrderSearchRepository $orderRepository;

    private FakeGateway $gateway;

    private FakeLogger $logger;

    private OrderEntityBuilder $orderBuilder;

    protected function setUp(): void
    {
        $this->orderRepository = new FakeOrderSearchRepository();
        $this->gateway = new FakeGateway();
        $this->logger = new FakeLogger();
        $this->orderBuilder = new OrderEntityBuilder();
    }

    public function testListeningOnCorrectEvent(): void
    {
        $this->assertArrayHasKey('state_machine.order.state_changed', CancelOrderSubscriber::getSubscribedEvents());
    }

    public function testMolliePaymentIsCancelledWhenTheOrderIsCancelled(): void
    {
        $order = $this->molliePaidOrder(new Payment('tr_123'));
        $this->orderRepository->add($order);

        $this->createSubscriber()->onOrderStateChanged($this->cancelEvent($order->getId()));

        self::assertSame(['tr_123'], $this->gateway->getCancelledPaymentIds());
    }

    public function testMollieOrderIsCancelledForAnOrdersApiPayment(): void
    {
        $payment = new Payment('tr_123');
        $payment->setOrderId('ord_123');
        $order = $this->molliePaidOrder($payment);
        $this->orderRepository->add($order);

        $this->createSubscriber()->onOrderStateChanged($this->cancelEvent($order->getId()));

        self::assertSame(['ord_123'], $this->gateway->getCancelledOrderIds());
        self::assertSame([], $this->gateway->getCancelledPaymentIds());
    }

    public function testNothingIsCancelledWhenTheStateIsOnlyLeft(): void
    {
        $order = $this->molliePaidOrder(new Payment('tr_123'));
        $this->orderRepository->add($order);

        $event = $this->stateChangeEvent(
            $order->getId(),
            StateMachineTransitionActions::ACTION_CANCEL,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE,
        );

        $this->createSubscriber()->onOrderStateChanged($event);

        self::assertSame([], $this->gateway->getCancelledPaymentIds());
    }

    public function testNothingIsCancelledForAnotherTransition(): void
    {
        $order = $this->molliePaidOrder(new Payment('tr_123'));
        $this->orderRepository->add($order);

        $event = $this->stateChangeEvent(
            $order->getId(),
            StateMachineTransitionActions::ACTION_COMPLETE,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
        );

        $this->createSubscriber()->onOrderStateChanged($event);

        self::assertSame([], $this->gateway->getCancelledPaymentIds());
    }

    public function testNothingIsCancelledWhenTheOrderNoLongerExists(): void
    {
        $this->createSubscriber()->onOrderStateChanged($this->cancelEvent('unknownorderid'));

        self::assertSame([], $this->gateway->getCancelledPaymentIds());
    }

    public function testNothingIsCancelledWhenAutomaticCancellationIsDisabled(): void
    {
        $order = $this->molliePaidOrder(new Payment('tr_123'));
        $this->orderRepository->add($order);

        $subscriber = $this->createSubscriber(new PaymentSettings('', 0));

        $subscriber->onOrderStateChanged($this->cancelEvent($order->getId()));

        self::assertSame([], $this->gateway->getCancelledPaymentIds());
    }

    public function testNothingIsCancelledForAnOrderPaidWithoutMollie(): void
    {
        $order = $this->orderBuilder->getOrderWithoutMolliePayment(new OrderLineItemCollection());
        $this->orderRepository->add($order);

        $this->createSubscriber()->onOrderStateChanged($this->cancelEvent($order->getId()));

        self::assertSame([], $this->gateway->getCancelledPaymentIds());
    }

    public function testAPaymentThatCanNoLongerBeCancelledIsLoggedAsAWarning(): void
    {
        $order = $this->molliePaidOrder(new Payment('tr_123'));
        $this->orderRepository->add($order);
        $this->gateway->withCancelFailure(new ApiException(422, 'Unprocessable Entity', 'The payment cannot be canceled', 'payment'));

        $this->createSubscriber()->onOrderStateChanged($this->cancelEvent($order->getId()));

        self::assertTrue($this->logger->hasRecordThatContains('warning', 'no longer in a cancellable state'));
    }

    public function testAnyOtherMollieErrorIsLoggedAsAnError(): void
    {
        $order = $this->molliePaidOrder(new Payment('tr_123'));
        $this->orderRepository->add($order);
        $this->gateway->withCancelFailure(new ApiException(422, 'Unprocessable Entity', 'The amount is invalid', 'amount'));

        $this->createSubscriber()->onOrderStateChanged($this->cancelEvent($order->getId()));

        self::assertTrue($this->logger->hasRecordThatContains('error', 'Failed to auto-cancel'));
    }

    public function testAnUnreachableMollieApiIsLoggedInsteadOfBreakingTheStateChange(): void
    {
        $order = $this->molliePaidOrder(new Payment('tr_123'));
        $this->orderRepository->add($order);
        $this->gateway->withCancelFailure(new \RuntimeException('Mollie API not reachable'));

        $this->createSubscriber()->onOrderStateChanged($this->cancelEvent($order->getId()));

        self::assertTrue($this->logger->hasRecordThatContains('error', 'Failed to auto-cancel'));
    }

    private function createSubscriber(?PaymentSettings $paymentSettings = null): CancelOrderSubscriber
    {
        $settings = new FakeSettingsService(
            paymentSettings: $paymentSettings ?? new PaymentSettings('', 0, automaticCancellation: true),
        );

        return new CancelOrderSubscriber($settings, $this->gateway, $this->orderRepository, $this->logger);
    }

    private function molliePaidOrder(Payment $payment): OrderEntity
    {
        return $this->orderBuilder->getOrderWithMolliePayment(new OrderLineItemCollection(), $payment);
    }

    private function cancelEvent(string $orderId): StateMachineStateChangeEvent
    {
        return $this->stateChangeEvent(
            $orderId,
            StateMachineTransitionActions::ACTION_CANCEL,
            StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER,
        );
    }

    private function stateChangeEvent(string $orderId, string $transitionName, string $transitionSide): StateMachineStateChangeEvent
    {
        $stateMachine = new StateMachineEntity();
        $stateMachine->setId('state-machine-id');
        $stateMachine->setTechnicalName('order.state');

        $openState = new StateMachineStateEntity();
        $openState->setId('open-state-id');
        $openState->setTechnicalName('open');

        $cancelledState = new StateMachineStateEntity();
        $cancelledState->setId('cancelled-state-id');
        $cancelledState->setTechnicalName('cancelled');

        return new StateMachineStateChangeEvent(
            Context::createDefaultContext(),
            $transitionSide,
            new Transition('order', $orderId, $transitionName, 'stateId'),
            $stateMachine,
            $openState,
            $cancelledState,
        );
    }
}
